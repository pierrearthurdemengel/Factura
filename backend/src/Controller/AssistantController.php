<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Service\Assistant\AssistantService;
use App\Service\Assistant\FiscalKnowledgeBase;
use App\Service\Assistant\TaxSimulator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints de l'assistant comptable conversationnel.
 *
 * Fournit un chatbot fiscal intelligent avec base de connaissances,
 * simulateurs (micro vs reel, EI vs societe, estimation IR),
 * et integration LLM avec cache PostgreSQL.
 */
#[Route('/api/assistant')]
class AssistantController extends AbstractController
{
    public function __construct(
        private readonly AssistantService $assistantService,
        private readonly TaxSimulator $taxSimulator,
        private readonly FiscalKnowledgeBase $knowledgeBase,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Pose une question a l'assistant comptable.
     */
    #[Route('/ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array{question?: string, company_id?: string, conversation_id?: string, context?: array<string, mixed>} $data */
        $data = json_decode((string) $request->getContent(), true) ?? [];

        $question = $data['question'] ?? '';
        if ('' === $question) {
            return new JsonResponse(['error' => 'La question est requise.'], Response::HTTP_BAD_REQUEST);
        }

        $company = $this->getCompany($data['company_id'] ?? '');
        if (null === $company) {
            return new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $conversationId = $data['conversation_id'] ?? null;
        /** @var array<string, mixed> $context */
        $context = $data['context'] ?? [];

        $result = $this->assistantService->ask($user, $company, $question, $conversationId, $context);

        return new JsonResponse($result);
    }

    /**
     * Recupere l'historique d'une conversation.
     */
    #[Route('/conversations/{conversationId}', methods: ['GET'])]
    public function conversation(string $conversationId): JsonResponse
    {
        $messages = $this->assistantService->getConversationHistory($conversationId);

        return new JsonResponse(['messages' => $messages]);
    }

    /**
     * Liste les conversations de l'utilisateur.
     */
    #[Route('/conversations', methods: ['GET'])]
    public function conversations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $company = $this->getCompany($request->query->getString('company_id'));
        if (null === $company) {
            return new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $conversations = $this->assistantService->listConversations($user, $company);

        return new JsonResponse(['conversations' => $conversations]);
    }

    /**
     * Simulation micro vs reel.
     */
    #[Route('/simulate/micro-vs-reel', methods: ['POST'])]
    public function simulateMicroVsReel(Request $request): JsonResponse
    {
        /** @var array{turnover?: string, expenses?: string, activity_type?: string} $data */
        $data = json_decode((string) $request->getContent(), true) ?? [];

        $turnover = $data['turnover'] ?? '';
        $expenses = $data['expenses'] ?? '';
        $activityType = $data['activity_type'] ?? 'bnc';

        if ('' === $turnover || '' === $expenses) {
            return new JsonResponse(
                ['error' => 'Les parametres turnover et expenses sont requis.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse($this->taxSimulator->simulateMicroVsReel($turnover, $expenses, $activityType));
    }

    /**
     * Simulation EI vs societe.
     */
    #[Route('/simulate/ei-vs-societe', methods: ['POST'])]
    public function simulateEiVsSociete(Request $request): JsonResponse
    {
        /** @var array{turnover?: string, expenses?: string, salary?: string} $data */
        $data = json_decode((string) $request->getContent(), true) ?? [];

        $turnover = $data['turnover'] ?? '';
        $expenses = $data['expenses'] ?? '';
        $salary = $data['salary'] ?? '';

        if ('' === $turnover || '' === $expenses || '' === $salary) {
            return new JsonResponse(
                ['error' => 'Les parametres turnover, expenses et salary sont requis.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse($this->taxSimulator->simulateEiVsSociete($turnover, $expenses, $salary));
    }

    /**
     * Estimation impot sur le revenu.
     */
    #[Route('/simulate/income-tax', methods: ['POST'])]
    public function simulateIncomeTax(Request $request): JsonResponse
    {
        /** @var array{taxable_income?: string, parts?: int} $data */
        $data = json_decode((string) $request->getContent(), true) ?? [];

        $taxableIncome = $data['taxable_income'] ?? '';
        if ('' === $taxableIncome) {
            return new JsonResponse(
                ['error' => 'Le parametre taxable_income est requis.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $parts = $data['parts'] ?? 1;

        return new JsonResponse($this->taxSimulator->estimateIncomeTax($taxableIncome, $parts));
    }

    /**
     * Retourne les taux et baremes en vigueur.
     */
    #[Route('/rates', methods: ['GET'])]
    public function rates(): JsonResponse
    {
        return new JsonResponse($this->knowledgeBase->getRates());
    }

    /**
     * Retourne les regles de deductibilite.
     */
    #[Route('/deductibility', methods: ['GET'])]
    public function deductibility(): JsonResponse
    {
        return new JsonResponse($this->knowledgeBase->getDeductibilityRules());
    }

    /**
     * Recupere l'entreprise depuis son identifiant.
     */
    private function getCompany(string $companyId): ?Company
    {
        if ('' === $companyId) {
            return null;
        }

        return $this->em->getRepository(Company::class)->find($companyId);
    }
}
