<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Company;
use App\Service\Dashboard\CashFlowPredictor;
use App\Service\Dashboard\ClientPaymentScorer;
use App\Service\Dashboard\DashboardMetrics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints du tableau de bord financier avance.
 *
 * Fournit les metriques de CA, les projections de tresorerie,
 * et le scoring de fiabilite des clients.
 */
#[Route('/api/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardMetrics $metrics,
        private readonly CashFlowPredictor $cashFlowPredictor,
        private readonly ClientPaymentScorer $clientScorer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Metriques principales du tableau de bord.
     */
    #[Route('/metrics', methods: ['GET'])]
    public function metrics(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);
        if (!$company instanceof Company) {
            return new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $from = $this->parseDate($request->query->getString('from'));
        $to = $this->parseDate($request->query->getString('to'));

        if (null === $from || null === $to) {
            return new JsonResponse(
                ['error' => 'Parametres from et to requis (format YYYY-MM-DD).'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse($this->metrics->getMetrics($company, $from, $to));
    }

    /**
     * CA mensuel sur une annee.
     */
    #[Route('/monthly', methods: ['GET'])]
    public function monthlyTurnover(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);
        if (!$company instanceof Company) {
            return new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $year = $request->query->getInt('year', (int) date('Y'));

        return new JsonResponse($this->metrics->getMonthlyTurnover($company, $year));
    }

    /**
     * Repartition du CA par client.
     */
    #[Route('/clients', methods: ['GET'])]
    public function clientBreakdown(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);
        if (!$company instanceof Company) {
            return new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $from = $this->parseDate($request->query->getString('from'));
        $to = $this->parseDate($request->query->getString('to'));

        if (null === $from || null === $to) {
            return new JsonResponse(
                ['error' => 'Parametres from et to requis (format YYYY-MM-DD).'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse($this->metrics->getTurnoverByClient($company, $from, $to));
    }

    /**
     * Projection de tresorerie a J+30/60/90.
     */
    #[Route('/cashflow', methods: ['GET'])]
    public function cashFlow(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);
        if (!$company instanceof Company) {
            return new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $balance = $request->query->getString('balance', '0.00');
        $threshold = $request->query->getString('threshold', '1000.00');

        return new JsonResponse($this->cashFlowPredictor->predict($company, $balance, $threshold));
    }

    /**
     * Scoring de fiabilite d'un client.
     */
    #[Route('/client-score/{clientId}', methods: ['GET'])]
    public function clientScore(string $clientId): JsonResponse
    {
        $client = $this->em->getRepository(Client::class)->find($clientId);
        if (!$client instanceof Client) {
            return new JsonResponse(['error' => 'Client non trouve.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->clientScorer->getProfile($client));
    }

    /**
     * Recupere l'entreprise depuis le parametre company_id.
     */
    private function getCompany(Request $request): ?Company
    {
        $companyId = $request->query->getString('company_id');
        if ('' === $companyId) {
            return null;
        }

        return $this->em->getRepository(Company::class)->find($companyId);
    }

    /**
     * Parse une date au format YYYY-MM-DD.
     */
    private function parseDate(string $date): ?\DateTimeImmutable
    {
        if ('' === $date) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return false === $parsed ? null : $parsed->setTime(0, 0);
    }
}
