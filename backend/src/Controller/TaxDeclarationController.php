<?php

namespace App\Controller;

use App\Entity\Company;
use App\Service\Tax\UrssafCalculator;
use App\Service\Tax\VatCalculator;
use App\Service\Tax\VatDeclarationGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints pour les declarations fiscales (TVA et URSSAF).
 *
 * Permet de calculer la TVA collectee/deductible, generer les
 * formulaires CA3/CA12, et estimer les cotisations URSSAF.
 */
#[Route('/api/tax')]
class TaxDeclarationController extends AbstractController
{
    private const MSG_COMPANY_NOT_FOUND = 'Entreprise non trouvee.';
    private const MSG_DATE_PARAMS_REQUIRED = 'Parametres from et to requis (format YYYY-MM-DD).';

    public function __construct(
        private readonly VatCalculator $vatCalculator,
        private readonly VatDeclarationGenerator $vatDeclarationGenerator,
        private readonly UrssafCalculator $urssafCalculator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Calcule le solde de TVA pour une periode donnee.
     */
    #[Route('/vat/balance', methods: ['GET'])]
    public function vatBalance(Request $request): JsonResponse
    {
        $validationError = $this->validateCompanyAndDateRange($request);
        if (null !== $validationError) {
            return $validationError;
        }

        /** @var Company $company */
        $company = $this->getCompany($request);
        /** @var \DateTimeImmutable $from */
        $from = $this->parseDate($request->query->getString('from'));
        /** @var \DateTimeImmutable $to */
        $to = $this->parseDate($request->query->getString('to'));

        $balance = $this->vatCalculator->calculateVatBalance($company, $from, $to);

        return new JsonResponse($balance);
    }

    /**
     * Genere les donnees du formulaire CA3 (TVA mensuelle/trimestrielle).
     */
    #[Route('/vat/ca3', methods: ['GET'])]
    public function generateCA3(Request $request): JsonResponse
    {
        $validationError = $this->validateCompanyAndDateRange($request);
        if (null !== $validationError) {
            return $validationError;
        }

        /** @var Company $company */
        $company = $this->getCompany($request);
        /** @var \DateTimeImmutable $from */
        $from = $this->parseDate($request->query->getString('from'));
        /** @var \DateTimeImmutable $to */
        $to = $this->parseDate($request->query->getString('to'));

        $ca3 = $this->vatDeclarationGenerator->generateCA3($company, $from, $to);

        return new JsonResponse($ca3);
    }

    /**
     * Genere les donnees du formulaire CA12 (TVA annuelle simplifiee).
     */
    #[Route('/vat/ca12', methods: ['GET'])]
    public function generateCA12(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);
        if (!$company instanceof Company) {
            return new JsonResponse(['error' => self::MSG_COMPANY_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $year = $request->query->getInt('year', (int) date('Y'));

        $ca12 = $this->vatDeclarationGenerator->generateCA12($company, $year);

        return new JsonResponse($ca12);
    }

    /**
     * Calcule les cotisations URSSAF pour un auto-entrepreneur.
     */
    #[Route('/urssaf/contributions', methods: ['GET'])]
    public function urssafContributions(Request $request): JsonResponse
    {
        $validationError = $this->validateCompanyAndDateRange($request);
        if (null !== $validationError) {
            return $validationError;
        }

        /** @var Company $company */
        $company = $this->getCompany($request);
        /** @var \DateTimeImmutable $from */
        $from = $this->parseDate($request->query->getString('from'));
        /** @var \DateTimeImmutable $to */
        $to = $this->parseDate($request->query->getString('to'));
        $activityType = $request->query->getString('activityType', UrssafCalculator::ACTIVITY_BNC_LIBERAL);

        try {
            $contributions = $this->urssafCalculator->calculateContributions(
                $company,
                $activityType,
                $from,
                $to,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($contributions);
    }

    /**
     * Retourne les echeances de declaration URSSAF pour une annee.
     */
    #[Route('/urssaf/deadlines', methods: ['GET'])]
    public function urssafDeadlines(Request $request): JsonResponse
    {
        $year = $request->query->getInt('year', (int) date('Y'));
        $frequency = $request->query->getString('frequency', UrssafCalculator::FREQUENCY_QUARTERLY);

        $deadlines = $this->urssafCalculator->getDeclarationDeadlines($year, $frequency);

        return new JsonResponse(['year' => $year, 'frequency' => $frequency, 'deadlines' => $deadlines]);
    }

    /**
     * Valide que le company_id et les dates from/to sont presents et valides.
     */
    private function validateCompanyAndDateRange(Request $request): ?JsonResponse
    {
        $company = $this->getCompany($request);
        if (!$company instanceof Company) {
            return new JsonResponse(['error' => self::MSG_COMPANY_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $from = $this->parseDate($request->query->getString('from'));
        $to = $this->parseDate($request->query->getString('to'));

        if (null === $from || null === $to) {
            return new JsonResponse(
                ['error' => self::MSG_DATE_PARAMS_REQUIRED],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return null;
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
