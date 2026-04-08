<?php

namespace App\Service\Billing;

use App\Entity\BillingPlan;
use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcule les frais annuels du plan "Succes Partage".
 * Gratuit en dessous de 50 000 EUR de CA annuel facture.
 * 0.1% au-dela, plafonne a 588 EUR/an (49 EUR/mois).
 */
class RevenueBasedBilling
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Calcule le montant annuel du pour un plan succes partage.
     *
     * @return array{annualRevenue: string, fee: string, savingsVsPro: string, effectiveRate: string, breakdown: array{threshold: string, rate: string, cap: string}}
     */
    public function calculateAnnualFee(Company $company, int $year): array
    {
        $annualRevenue = $this->getAnnualRevenue($company, $year);

        $fee = $this->computeFee($annualRevenue);

        // Comparaison avec le plan Pro fixe (14 EUR/mois = 168 EUR/an)
        $proAnnual = bcmul(BillingPlan::PRICE_PRO_MONTHLY, '12', 2);
        $savings = bcsub($proAnnual, $fee, 2);

        // Taux effectif
        $effectiveRate = '0.00';
        if (bccomp($annualRevenue, '0', 2) > 0) {
            $effectiveRate = bcmul(bcdiv($fee, $annualRevenue, 6), '100', 4);
        }

        return [
            'annualRevenue' => $annualRevenue,
            'fee' => $fee,
            'savingsVsPro' => $savings,
            'effectiveRate' => $effectiveRate,
            'breakdown' => [
                'threshold' => BillingPlan::SUCCESS_THRESHOLD,
                'rate' => BillingPlan::SUCCESS_RATE,
                'cap' => BillingPlan::SUCCESS_CAP_ANNUAL,
            ],
        ];
    }

    /**
     * Simule les frais pour un CA donne (sans lire la BDD).
     * Utile pour le simulateur interactif sur la page tarifs.
     *
     * @param numeric-string $annualRevenue
     *
     * @return array{fee: string, effectiveRate: string, monthlyEquivalent: string}
     */
    public function simulate(string $annualRevenue): array
    {
        $fee = $this->computeFee($annualRevenue);

        $effectiveRate = '0.00';
        if (bccomp($annualRevenue, '0', 2) > 0) {
            $effectiveRate = bcmul(bcdiv($fee, $annualRevenue, 6), '100', 4);
        }

        $monthlyEquivalent = bcdiv($fee, '12', 2);

        return [
            'fee' => $fee,
            'effectiveRate' => $effectiveRate,
            'monthlyEquivalent' => $monthlyEquivalent,
        ];
    }

    /**
     * Calcule les frais du plan Cabinet pour un nombre de clients donnes.
     *
     * @return array{monthlyFee: string, perClientCost: string, annualFee: string}
     */
    public function calculateCabinetFee(int $activeClients): array
    {
        $extra = max(0, $activeClients - BillingPlan::CABINET_INCLUDED_CLIENTS);
        $extraCost = bcmul((string) $extra, BillingPlan::PRICE_CABINET_PER_CLIENT, 2);
        $monthlyFee = bcadd(BillingPlan::PRICE_CABINET_BASE, $extraCost, 2);
        $annualFee = bcmul($monthlyFee, '12', 2);

        $perClientCost = '0.00';
        if ($activeClients > 0) {
            $perClientCost = bcdiv($monthlyFee, (string) $activeClients, 2);
        }

        return [
            'monthlyFee' => $monthlyFee,
            'perClientCost' => $perClientCost,
            'annualFee' => $annualFee,
        ];
    }

    /**
     * Applique la formule du succes partage.
     *
     * @param numeric-string $annualRevenue
     *
     * @return numeric-string
     */
    private function computeFee(string $annualRevenue): string
    {
        // En dessous du seuil : 0 EUR
        if (bccomp($annualRevenue, BillingPlan::SUCCESS_THRESHOLD, 2) <= 0) {
            return '0.00';
        }

        // Montant taxable = CA - seuil
        $taxable = bcsub($annualRevenue, BillingPlan::SUCCESS_THRESHOLD, 2);

        // Frais = 0.1% du montant taxable
        $fee = bcmul($taxable, BillingPlan::SUCCESS_RATE, 2);

        // Plafonnement a 588 EUR/an
        if (bccomp($fee, BillingPlan::SUCCESS_CAP_ANNUAL, 2) > 0) {
            return BillingPlan::SUCCESS_CAP_ANNUAL;
        }

        return $fee;
    }

    /**
     * Recupere le CA annuel facture (SENT + PAID) pour une entreprise.
     *
     * @return numeric-string
     */
    private function getAnnualRevenue(Company $company, int $year): string
    {
        $companyId = $company->getId();
        if (null === $companyId) {
            return '0.00';
        }

        $conn = $this->em->getConnection();

        $result = $conn->executeQuery(
            "SELECT COALESCE(SUM(CAST(total_excluding_tax AS DECIMAL(15,2))), 0) as revenue
             FROM invoices
             WHERE seller_id = :seller_id
               AND EXTRACT(YEAR FROM issue_date) = :year
               AND status IN ('SENT', 'ACKNOWLEDGED', 'PAID')",
            ['seller_id' => $companyId->toRfc4122(), 'year' => $year],
        )->fetchOne();

        $revenue = (string) $result;

        return is_numeric($revenue) ? $revenue : '0.00';
    }
}
