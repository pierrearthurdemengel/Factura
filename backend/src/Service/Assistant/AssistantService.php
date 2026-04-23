<?php

namespace App\Service\Assistant;

use App\Entity\AssistantConversation;
use App\Entity\AssistantMessage;
use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service principal de l'assistant comptable conversationnel.
 *
 * Orchestre le flux complet : normalisation de la question, recherche en cache,
 * interrogation de la base de connaissances, appel LLM si necessaire,
 * mise en cache de la reponse, et persistance de la conversation.
 *
 * Strategie de cout optimisee :
 * 1. Cache PostgreSQL (TTL 30j) → cout 0
 * 2. Base de connaissances locale → cout 0
 * 3. Claude Haiku (triage simple) → ~0.002 EUR
 * 4. Claude Sonnet (simulations complexes) → ~0.01 EUR
 */
class AssistantService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FiscalKnowledgeBase $knowledgeBase,
        private readonly AssistantCacheService $cacheService,
        private readonly LlmClient $llmClient,
        private readonly TaxSimulator $taxSimulator,
    ) {
    }

    /**
     * Traite une question utilisateur et retourne la reponse.
     *
     * @param array<string, mixed> $context Contexte entreprise optionnel
     *
     * @return array{
     *     answer: string,
     *     references: list<string>,
     *     category: string,
     *     actions: list<string>,
     *     source: string,
     *     conversationId: string
     * }
     */
    public function ask(
        User $user,
        Company $company,
        string $question,
        ?string $conversationId = null,
        array $context = [],
    ): array {
        // Recuperer ou creer la conversation
        $conversation = $this->getOrCreateConversation($user, $company, $conversationId);

        // Sauvegarder la question utilisateur
        $userMessage = new AssistantMessage();
        $userMessage->setRole(AssistantMessage::ROLE_USER);
        $userMessage->setContent($question);
        $conversation->addMessage($userMessage);

        // Normaliser et categoriser
        $normalized = $this->knowledgeBase->normalizeQuestion($question);
        $category = $this->knowledgeBase->categorize($normalized);

        $convId = (string) $conversation->getId();

        // Resoudre la reponse par simulation, cache, base de connaissances ou LLM
        $resolved = $this->resolveAnswer($question, $normalized, $category, $context);

        $this->saveAssistantMessage($conversation, $resolved['data'], $category, $resolved['source']);
        $this->em->flush();

        return $this->buildResponse($resolved['data'], $resolved['category'], $resolved['source'], $convId);
    }

    /**
     * Recupere l'historique d'une conversation.
     *
     * @return list<array{role: string, content: string, createdAt: string, metadata: array<string, mixed>|null}>
     */
    public function getConversationHistory(string $conversationId): array
    {
        $conversation = $this->em->getRepository(AssistantConversation::class)->find($conversationId);
        if (null === $conversation) {
            return [];
        }

        $messages = [];
        foreach ($conversation->getMessages() as $message) {
            $messages[] = [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format('c'),
                'metadata' => $message->getMetadata(),
            ];
        }

        return $messages;
    }

    /**
     * Liste les conversations d'un utilisateur.
     *
     * @return list<array{id: string, title: string|null, createdAt: string, updatedAt: string, messageCount: int}>
     */
    public function listConversations(User $user, Company $company): array
    {
        $conversations = $this->em->getRepository(AssistantConversation::class)->findBy(
            ['user' => $user, 'company' => $company],
            ['updatedAt' => 'DESC'],
        );

        $result = [];
        foreach ($conversations as $conversation) {
            $result[] = [
                'id' => (string) $conversation->getId(),
                'title' => $conversation->getTitle(),
                'createdAt' => $conversation->getCreatedAt()->format('c'),
                'updatedAt' => $conversation->getUpdatedAt()->format('c'),
                'messageCount' => $conversation->getMessages()->count(),
            ];
        }

        return $result;
    }

    /**
     * Resout la reponse par simulation, cache, base de connaissances ou LLM.
     *
     * @param array<string, mixed> $context
     *
     * @return array{data: array<string, mixed>, source: string, category: string}
     */
    private function resolveAnswer(string $question, string $normalized, string $category, array $context): array
    {
        // Detecter les demandes de simulation
        $simulationResult = $this->detectAndRunSimulation($normalized, $context);
        if (null !== $simulationResult) {
            return ['data' => $simulationResult, 'source' => 'simulation', 'category' => $category];
        }

        // 1. Chercher en cache
        $cached = $this->cacheService->get($question);
        if (null !== $cached) {
            return ['data' => $cached, 'source' => 'cache', 'category' => $category];
        }

        // 2. Chercher dans la base de connaissances locale ou appeler le LLM
        return $this->resolveFromKnowledgeBaseOrLlm($question, $normalized, $category, $context);
    }

    /**
     * Resout la reponse via la base de connaissances ou le LLM en fallback.
     *
     * @param array<string, mixed> $context
     *
     * @return array{data: array<string, mixed>, source: string, category: string}
     */
    private function resolveFromKnowledgeBaseOrLlm(string $question, string $normalized, string $category, array $context): array
    {
        $kbAnswer = $this->knowledgeBase->findAnswer($normalized);
        if (null !== $kbAnswer) {
            $this->cacheService->put($question, $kbAnswer, $category);

            return ['data' => $kbAnswer, 'source' => 'knowledge_base', 'category' => $kbAnswer['category']];
        }

        $llmAnswer = $this->llmClient->ask($question, $category, $context);
        $response = [
            'answer' => $llmAnswer['answer'],
            'references' => $llmAnswer['references'],
            'category' => $category,
            'actions' => $llmAnswer['actions'],
        ];

        $this->cacheService->put($question, $response, $category);

        return ['data' => $response, 'source' => $llmAnswer['model'], 'category' => $category];
    }

    /**
     * Construit la reponse structuree a partir des donnees brutes.
     *
     * @param array<string, mixed> $data
     *
     * @return array{answer: string, references: list<string>, category: string, actions: list<string>, source: string, conversationId: string}
     */
    private function buildResponse(array $data, string $category, string $source, string $conversationId): array
    {
        /** @var list<string> $references */
        $references = $data['references'] ?? [];
        /** @var list<string> $actions */
        $actions = $data['actions'] ?? [];

        return [
            'answer' => (string) ($data['answer'] ?? ''),
            'references' => $references,
            'category' => $category,
            'actions' => $actions,
            'source' => $source,
            'conversationId' => $conversationId,
        ];
    }

    /**
     * Recupere ou cree une conversation.
     */
    private function getOrCreateConversation(User $user, Company $company, ?string $conversationId): AssistantConversation
    {
        if (null !== $conversationId) {
            $existing = $this->em->getRepository(AssistantConversation::class)->find($conversationId);
            if (null !== $existing) {
                return $existing;
            }
        }

        $conversation = new AssistantConversation();
        $conversation->setUser($user);
        $conversation->setCompany($company);
        $this->em->persist($conversation);

        return $conversation;
    }

    /**
     * Sauvegarde la reponse de l'assistant dans la conversation.
     *
     * @param array<string, mixed> $response
     */
    private function saveAssistantMessage(
        AssistantConversation $conversation,
        array $response,
        string $category,
        string $source,
    ): void {
        $message = new AssistantMessage();
        $message->setRole(AssistantMessage::ROLE_ASSISTANT);
        $message->setContent((string) ($response['answer'] ?? ''));
        $message->setMetadata([
            'category' => $category,
            'source' => $source,
            'references' => $response['references'] ?? [],
            'actions' => $response['actions'] ?? [],
        ]);
        $conversation->addMessage($message);

        // Definir le titre de la conversation a partir de la premiere question
        if (null === $conversation->getTitle() && $conversation->getMessages()->count() <= 2) {
            $firstMessage = $conversation->getMessages()->first();
            if (false !== $firstMessage) {
                $title = mb_substr($firstMessage->getContent(), 0, 80);
                $conversation->setTitle($title);
            }
        }
    }

    /**
     * Detecte les demandes de simulation dans la question et execute le simulateur.
     *
     * @param array<string, mixed> $context
     *
     * @return array{answer: string, references: list<string>, actions: list<string>}|null
     */
    private function detectAndRunSimulation(string $normalizedQuestion, array $context): ?array
    {
        $type = $this->detectSimulationType($normalizedQuestion);
        if (null === $type) {
            return null;
        }

        return $this->executeSimulation($type, $context);
    }

    /**
     * Detecte le type de simulation demandee dans la question.
     */
    private function detectSimulationType(string $normalizedQuestion): ?string
    {
        $patterns = [
            'micro_vs_reel' => ['simuler micro', 'simulation micro vs reel'],
            'ei_vs_societe' => ['simuler passage societe', 'simulation ei vs societe'],
            'ir' => ['estimer impot', 'estimation impot revenu'],
        ];

        foreach ($patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalizedQuestion, $keyword)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Execute la simulation du type donne et retourne le resultat formate.
     *
     * @param array<string, mixed> $context
     *
     * @return array{answer: string, references: list<string>, actions: list<string>}
     */
    private function executeSimulation(string $type, array $context): array
    {
        return match ($type) {
            'micro_vs_reel' => [
                'answer' => $this->formatSimulationResult('micro_vs_reel', $this->taxSimulator->simulateMicroVsReel(
                    (string) ($context['turnover'] ?? '50000'),
                    (string) ($context['expenses'] ?? '15000'),
                    (string) ($context['activityType'] ?? 'bnc'),
                )),
                'references' => ['Article 50-0 du CGI', 'Article 102 ter du CGI'],
                'actions' => ['Modifier les parametres de simulation', 'Consulter un expert-comptable'],
            ],
            'ei_vs_societe' => [
                'answer' => $this->formatSimulationResult('ei_vs_societe', $this->taxSimulator->simulateEiVsSociete(
                    (string) ($context['turnover'] ?? '80000'),
                    (string) ($context['expenses'] ?? '20000'),
                    (string) ($context['salary'] ?? '30000'),
                )),
                'references' => ['Article 206 du CGI', 'Article 8 du CGI'],
                'actions' => ['Modifier les parametres de simulation', 'Consulter un expert-comptable'],
            ],
            default => [
                'answer' => $this->formatSimulationResult('ir', $this->taxSimulator->estimateIncomeTax(
                    (string) ($context['taxableIncome'] ?? '40000'),
                    (int) ($context['parts'] ?? 1),
                )),
                'references' => ['Article 197 du CGI'],
                'actions' => ['Modifier le revenu imposable', 'Modifier le nombre de parts'],
            ],
        };
    }

    /**
     * Formate le resultat d'une simulation en texte lisible.
     *
     * @param array<string, mixed> $result
     */
    private function formatSimulationResult(string $type, array $result): string
    {
        return match ($type) {
            'micro_vs_reel' => sprintf(
                "Simulation Micro vs Reel :\n\n"
                . "Micro-entrepreneur :\n- CA : %s EUR\n- Abattement : %s EUR\n- Cotisations : %s EUR\n- Revenu net : %s EUR\n\n"
                . "Regime reel :\n- CA : %s EUR\n- Charges reelles : %s EUR\n- Cotisations : %s EUR\n- Revenu net : %s EUR\n\n"
                . "%s\nEconomie estimee : %s EUR/an.",
                $result['micro']['turnover'] ?? '0', $result['micro']['abatement'] ?? '0',
                $result['micro']['cotisations'] ?? '0', $result['micro']['netIncome'] ?? '0',
                $result['reel']['turnover'] ?? '0', $result['reel']['expenses'] ?? '0',
                $result['reel']['cotisations'] ?? '0', $result['reel']['netIncome'] ?? '0',
                $result['recommendation'] ?? '', $result['savings'] ?? '0',
            ),
            'ei_vs_societe' => sprintf(
                "Simulation EI vs Societe :\n\n"
                . "Entreprise individuelle :\n- Benefice : %s EUR\n- Cotisations : %s EUR\n- IR : %s EUR\n- Net apres impots : %s EUR\n\n"
                . "Societe (SASU/EURL IS) :\n- Benefice : %s EUR\n- IS : %s EUR\n- Remuneration : %s EUR\n- Dividendes : %s EUR\n- Net apres impots : %s EUR\n\n"
                . "%s\nEconomie estimee : %s EUR/an.",
                $result['ei']['benefice'] ?? '0', $result['ei']['cotisations'] ?? '0',
                $result['ei']['ir'] ?? '0', $result['ei']['netAfterTax'] ?? '0',
                $result['societe']['benefice'] ?? '0', $result['societe']['is'] ?? '0',
                $result['societe']['salary'] ?? '0', $result['societe']['dividendes'] ?? '0',
                $result['societe']['netAfterTax'] ?? '0',
                $result['recommendation'] ?? '', $result['savings'] ?? '0',
            ),
            'ir' => sprintf(
                "Estimation de l'impot sur le revenu :\n\n"
                . "Revenu imposable : %s EUR\n"
                . "Nombre de parts : %s\n"
                . "Quotient familial : %s EUR\n\n"
                . "Impot estime : %s EUR\n"
                . "Taux marginal : %s\n"
                . 'Taux effectif : %s',
                $result['taxableIncome'] ?? '0', (string) ($result['parts'] ?? 1),
                $result['quotient'] ?? '0', $result['tax'] ?? '0',
                $result['marginalRate'] ?? '0%', $result['effectiveRate'] ?? '0%',
            ),
            default => 'Resultat de la simulation non disponible.',
        };
    }
}
