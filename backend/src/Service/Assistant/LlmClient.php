<?php

namespace App\Service\Assistant;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client LLM avec routage intelligent entre modeles.
 *
 * Utilise Claude Haiku pour le triage et la categorisation simple,
 * et Claude Sonnet pour les simulations fiscales complexes.
 * Le routage est base sur la categorie de la question.
 *
 * Cout moyen optimise : ~0.004 EUR par question au lieu de 0.01 EUR.
 */
class LlmClient
{
    // Modeles disponibles
    public const MODEL_HAIKU = 'haiku';
    public const MODEL_SONNET = 'sonnet';

    // Categories necessitant Sonnet (complexe)
    private const SONNET_CATEGORIES = [
        FiscalKnowledgeBase::CATEGORY_REGIME,
        FiscalKnowledgeBase::CATEGORY_IS,
    ];

    private readonly string $apiKey;
    private readonly bool $enabled;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        string $anthropicApiKey = '',
    ) {
        $this->apiKey = $anthropicApiKey;
        $this->enabled = '' !== $this->apiKey;
    }

    /**
     * Determine le modele optimal pour une question categorisee.
     */
    public function selectModel(string $category): string
    {
        if (\in_array($category, self::SONNET_CATEGORIES, true)) {
            return self::MODEL_SONNET;
        }

        return self::MODEL_HAIKU;
    }

    /**
     * Indique si le client LLM est active (cle API configuree).
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Envoie une question au LLM et retourne la reponse structuree.
     *
     * @param string               $question Question en langage naturel
     * @param string               $category Categorie detectee
     * @param array<string, mixed> $context  Contexte utilisateur (entreprise, CA, etc.)
     *
     * @return array{answer: string, references: list<string>, actions: list<string>, model: string}
     */
    public function ask(string $question, string $category, array $context = []): array
    {
        if (!$this->enabled) {
            return $this->getFallbackResponse($question, $category);
        }

        $model = $this->selectModel($category);

        try {
            $systemPrompt = $this->buildSystemPrompt($context);
            $response = $this->callApi($model, $systemPrompt, $question);

            return array_merge($response, ['model' => $model]);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur appel LLM : {message}', [
                'message' => $e->getMessage(),
                'model' => $model,
                'category' => $category,
            ]);

            return $this->getFallbackResponse($question, $category);
        }
    }

    /**
     * Reponse de secours quand le LLM n'est pas disponible.
     *
     * @return array{answer: string, references: list<string>, actions: list<string>, model: string}
     */
    private function getFallbackResponse(string $question, string $category): array
    {
        return [
            'answer' => sprintf(
                'Je n\'ai pas pu consulter l\'assistant en ligne pour votre question sur le theme "%s". '
                . 'Consultez la base de connaissances integree ou reformulez votre question.',
                $category,
            ),
            'references' => [],
            'actions' => [],
            'model' => 'fallback',
        ];
    }

    /**
     * Construit le prompt systeme avec le contexte utilisateur.
     *
     * @param array<string, mixed> $context
     */
    private function buildSystemPrompt(array $context): string
    {
        $base = 'Tu es un assistant comptable et fiscal specialise dans la reglementation francaise. '
            . 'Tu reponds de maniere precise et structuree, en citant les articles de loi et les references BOI pertinentes. '
            . 'Tu fournis des actions concretes quand c\'est possible. '
            . 'Reponds toujours en francais.';

        if ([] !== $context) {
            $contextParts = [];
            if (isset($context['companyName'])) {
                $contextParts[] = sprintf('Entreprise : %s', $context['companyName']);
            }
            if (isset($context['legalForm'])) {
                $contextParts[] = sprintf('Forme juridique : %s', $context['legalForm']);
            }
            if (isset($context['turnover'])) {
                $contextParts[] = sprintf('CA annuel : %s EUR', $context['turnover']);
            }

            if ([] !== $contextParts) {
                $base .= "\n\nContexte de l'utilisateur :\n" . implode("\n", $contextParts);
            }
        }

        return $base;
    }

    /**
     * Appelle l'API Anthropic.
     *
     * @return array{answer: string, references: list<string>, actions: list<string>}
     */
    private function callApi(string $model, string $systemPrompt, string $question): array
    {
        $modelId = self::MODEL_SONNET === $model
            ? 'claude-sonnet-4-6'
            : 'claude-haiku-4-5-20251001';

        $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $modelId,
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $question],
                ],
            ],
        ]);

        /** @var array{content: list<array{text: string}>} $data */
        $data = $response->toArray();
        $text = $data['content'][0]['text'] ?? '';

        return $this->parseResponse($text);
    }

    /**
     * Parse la reponse textuelle du LLM en structure.
     *
     * @return array{answer: string, references: list<string>, actions: list<string>}
     */
    private function parseResponse(string $text): array
    {
        $references = [];
        $actions = [];
        $answer = $text;

        // Extraire les references legales (pattern : Article XXX du CGI, BOI-XXX)
        if (preg_match_all('/(?:Article\s+[\d\w\s-]+du\s+\w+|BOI-[\w-]+)/', $text, $matches)) {
            $references = array_values(array_unique($matches[0]));
        }

        return [
            'answer' => $answer,
            'references' => $references,
            'actions' => $actions,
        ];
    }
}
