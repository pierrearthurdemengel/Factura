<?php

namespace App\Service\Tax;

use App\Entity\Company;

/**
 * Genere les donnees pre-remplies pour les declarations de TVA.
 *
 * Supporte deux formulaires :
 * - CA3 : declaration mensuelle ou trimestrielle (regime reel normal)
 * - CA12 : declaration annuelle simplifiee (regime simplifie)
 *
 * Les donnees sont retournees sous forme de tableaux structures
 * correspondant aux lignes des formulaires Cerfa.
 */
class VatDeclarationGenerator
{
    // Taux de TVA en vigueur en France metropolitaine
    public const RATE_NORMAL = '20';
    public const RATE_INTERMEDIATE = '10';
    public const RATE_REDUCED = '5.5';
    public const RATE_SUPER_REDUCED = '2.1';

    // Labels des journaux pour les formulaires
    private const JOURNAL_LABELS = [
        'VE' => 'Journal des ventes',
        'AC' => 'Journal des achats',
        'BQ' => 'Journal de banque',
        'OD' => 'Operations diverses',
    ];

    public function __construct(
        private readonly VatCalculator $vatCalculator,
    ) {
    }

    /**
     * Genere les donnees du formulaire CA3 (Cerfa 3310).
     *
     * Le CA3 est la declaration mensuelle ou trimestrielle de TVA
     * pour les entreprises au regime reel normal.
     *
     * @return array<string, mixed>
     */
    public function generateCA3(
        Company $company,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
    ): array {
        $collected = $this->vatCalculator->calculateCollectedVat($company, $periodStart, $periodEnd);
        $deductible = $this->vatCalculator->calculateDeductibleVat($company, $periodStart, $periodEnd);

        /** @var array<string, array{base: string, vat: string}> $byRate */
        $byRate = $collected['byRate'];

        // Ligne A : operations imposables par taux
        $line01 = $this->getRateValue($byRate, self::RATE_NORMAL, 'base');
        $line02 = $this->getRateValue($byRate, self::RATE_REDUCED, 'base');
        $line03 = $this->getRateValue($byRate, self::RATE_INTERMEDIATE, 'base');
        $line04 = $this->getRateValue($byRate, self::RATE_SUPER_REDUCED, 'base');

        // Base HT a taux zero (exonerations, etc.)
        $line05 = $this->getRateValue($byRate, '0', 'base');

        // Total chiffre d'affaires HT
        /** @var numeric-string $line08 */
        $line08 = '0.00';
        foreach ($byRate as $data) {
            /** @var numeric-string $base */
            $base = $data['base'];
            $line08 = bcadd($line08, $base, 2);
        }

        // TVA collectee par taux
        $line08a = $this->getRateValue($byRate, self::RATE_NORMAL, 'vat');
        $line09 = $this->getRateValue($byRate, self::RATE_REDUCED, 'vat');
        $line9b = $this->getRateValue($byRate, self::RATE_INTERMEDIATE, 'vat');
        $line10 = $this->getRateValue($byRate, self::RATE_SUPER_REDUCED, 'vat');

        // Total TVA brute collectee
        $line15 = $collected['total'];

        // TVA deductible
        $line20 = $deductible['total'];

        // Total TVA deductible
        $line23 = $deductible['total'];

        // TVA nette = collectee - deductible
        /** @var numeric-string $line15Num */
        $line15Num = $line15;
        /** @var numeric-string $line23Num */
        $line23Num = $line23;
        $balance = bcsub($line15Num, $line23Num, 2);
        $creditTva = '0.00';
        $netDue = '0.00';

        if (bccomp($balance, '0.00', 2) > 0) {
            $netDue = $balance;
        } else {
            $creditTva = bcmul($balance, '-1', 2);
        }

        return [
            'form' => 'CA3',
            'cerfa' => '3310',
            'company' => [
                'name' => $company->getName(),
                'siren' => $company->getSiren(),
                'vatNumber' => $company->getVatNumber(),
            ],
            'period' => [
                'start' => $periodStart->format('Y-m-d'),
                'end' => $periodEnd->format('Y-m-d'),
            ],
            'lines' => [
                // Section A : Montant des operations realisees
                'A01' => ['label' => 'Ventes, prestations de services (taux normal 20%)', 'base' => $line01],
                'A02' => ['label' => 'Ventes, prestations de services (taux reduit 5,5%)', 'base' => $line02],
                'A03' => ['label' => 'Ventes, prestations de services (taux intermediaire 10%)', 'base' => $line03],
                'A04' => ['label' => 'Ventes, prestations de services (taux super-reduit 2,1%)', 'base' => $line04],
                'A05' => ['label' => 'Autres operations non imposables', 'base' => $line05],
                'A08' => ['label' => 'Total du chiffre d\'affaires', 'amount' => $line08],

                // Section B : Decompte de la TVA a payer
                'B08A' => ['label' => 'TVA collectee a 20%', 'amount' => $line08a],
                'B09' => ['label' => 'TVA collectee a 5,5%', 'amount' => $line09],
                'B9B' => ['label' => 'TVA collectee a 10%', 'amount' => $line9b],
                'B10' => ['label' => 'TVA collectee a 2,1%', 'amount' => $line10],
                'B15' => ['label' => 'Total TVA brute', 'amount' => $line15],

                // Section C : TVA deductible
                'C20' => ['label' => 'TVA deductible sur biens et services', 'amount' => $line20],
                'C23' => ['label' => 'Total TVA deductible', 'amount' => $line23],

                // Section D : TVA nette
                'D25' => ['label' => 'Credit de TVA', 'amount' => $creditTva],
                'D28' => ['label' => 'TVA nette due', 'amount' => $netDue],
            ],
            'summary' => [
                'totalCollected' => $line15,
                'totalDeductible' => $line23,
                'netDue' => $netDue,
                'creditTva' => $creditTva,
                'invoiceCount' => $collected['invoiceCount'],
            ],
        ];
    }

    /**
     * Genere les donnees du formulaire CA12 (Cerfa 3517).
     *
     * Le CA12 est la declaration annuelle simplifiee de TVA
     * pour les entreprises au regime simplifie d'imposition.
     *
     * @return array<string, mixed>
     */
    public function generateCA12(
        Company $company,
        int $year,
    ): array {
        $periodStart = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $periodEnd = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        $collected = $this->vatCalculator->calculateCollectedVat($company, $periodStart, $periodEnd);
        $deductible = $this->vatCalculator->calculateDeductibleVat($company, $periodStart, $periodEnd);

        /** @var array<string, array{base: string, vat: string}> $byRate */
        $byRate = $collected['byRate'];

        // Total CA HT
        /** @var numeric-string $totalCa */
        $totalCa = '0.00';
        foreach ($byRate as $data) {
            /** @var numeric-string $base */
            $base = $data['base'];
            $totalCa = bcadd($totalCa, $base, 2);
        }

        // TVA nette
        /** @var numeric-string $collectedTotal */
        $collectedTotal = $collected['total'];
        /** @var numeric-string $deductibleTotal */
        $deductibleTotal = $deductible['total'];
        $balance = bcsub($collectedTotal, $deductibleTotal, 2);
        $creditTva = '0.00';
        $netDue = '0.00';

        if (bccomp($balance, '0.00', 2) > 0) {
            $netDue = $balance;
        } else {
            $creditTva = bcmul($balance, '-1', 2);
        }

        // Detail par taux
        $rateDetails = [];
        foreach ($byRate as $rate => $data) {
            $rateDetails[] = [
                'rate' => $rate,
                'base' => $data['base'],
                'vat' => $data['vat'],
            ];
        }

        return [
            'form' => 'CA12',
            'cerfa' => '3517',
            'company' => [
                'name' => $company->getName(),
                'siren' => $company->getSiren(),
                'vatNumber' => $company->getVatNumber(),
            ],
            'year' => $year,
            'period' => [
                'start' => $periodStart->format('Y-m-d'),
                'end' => $periodEnd->format('Y-m-d'),
            ],
            'turnover' => [
                'total' => $totalCa,
                'byRate' => $rateDetails,
            ],
            'vat' => [
                'collected' => $collected['total'],
                'deductible' => $deductible['total'],
                'netDue' => $netDue,
                'creditTva' => $creditTva,
            ],
            'summary' => [
                'totalTurnover' => $totalCa,
                'totalCollected' => $collected['total'],
                'totalDeductible' => $deductible['total'],
                'netDue' => $netDue,
                'creditTva' => $creditTva,
                'invoiceCount' => $collected['invoiceCount'],
            ],
        ];
    }

    /**
     * Retourne les labels des journaux comptables.
     *
     * @return array<string, string>
     */
    public static function getJournalLabels(): array
    {
        return self::JOURNAL_LABELS;
    }

    /**
     * Recupere une valeur pour un taux donne, ou '0.00' si absent.
     *
     * @param array<string, array{base: string, vat: string}> $byRate
     */
    private function getRateValue(array $byRate, string $rate, string $field): string
    {
        if (!isset($byRate[$rate])) {
            return '0.00';
        }

        return $byRate[$rate][$field];
    }
}
