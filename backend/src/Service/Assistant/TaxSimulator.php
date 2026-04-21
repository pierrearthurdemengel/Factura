<?php

namespace App\Service\Assistant;

/**
 * Simulateur fiscal pour les comparaisons de regimes.
 *
 * Permet de comparer micro vs reel, EI vs societe,
 * et d'estimer l'impot sur le revenu selon le bareme progressif.
 * Tous les calculs utilisent bcmath pour la precision financiere.
 */
class TaxSimulator
{
    private const IS_REDUCED_CEILING = self::IS_REDUCED_CEILING;

    /**
     * Simule la comparaison micro-entrepreneur vs regime reel.
     *
     * @param string $turnover       CA annuel HT
     * @param string $actualExpenses Charges reelles annuelles
     * @param string $activityType   Type d'activite (bic_sale, bic_service, bnc)
     *
     * @return array{
     *     micro: array{turnover: string, abatement: string, taxableIncome: string, cotisations: string, netIncome: string},
     *     reel: array{turnover: string, expenses: string, taxableIncome: string, cotisations: string, netIncome: string},
     *     recommendation: string,
     *     savings: string,
     *     details: string
     * }
     */
    public function simulateMicroVsReel(string $turnover, string $actualExpenses, string $activityType): array
    {
        // --- Micro-entrepreneur ---
        /** @var numeric-string $abatementRate */
        $abatementRate = $this->getMicroAbatementRate($activityType);
        /** @var numeric-string $turnoverNum */
        $turnoverNum = $turnover;
        /** @var numeric-string $abatement */
        $abatement = bcmul($turnoverNum, bcdiv($abatementRate, '100', 4), 2);
        /** @var numeric-string $microTaxable */
        $microTaxable = bcsub($turnoverNum, $abatement, 2);
        /** @var numeric-string $microCotisationRate */
        $microCotisationRate = $this->getUrssafRate($activityType);
        /** @var numeric-string $microCotisations */
        $microCotisations = bcmul($turnoverNum, bcdiv($microCotisationRate, '100', 4), 2);
        /** @var numeric-string $microNet */
        $microNet = bcsub($turnoverNum, $microCotisations, 2);

        // --- Regime reel ---
        /** @var numeric-string $expensesNum */
        $expensesNum = $actualExpenses;
        /** @var numeric-string $reelTaxable */
        $reelTaxable = bcsub($turnoverNum, $expensesNum, 2);
        if (bccomp($reelTaxable, '0', 2) < 0) {
            $reelTaxable = '0.00';
        }
        // Cotisations sociales du regime reel : ~45% du benefice (estimation)
        /** @var numeric-string $reelCotisations */
        $reelCotisations = bcmul($reelTaxable, '0.45', 2);
        /** @var numeric-string $reelNet */
        $reelNet = bcsub($reelTaxable, $reelCotisations, 2);

        // Comparaison
        /** @var numeric-string $savings */
        $savings = bcsub($reelNet, $microNet, 2);
        $reelBetter = bccomp($savings, '0', 2) > 0;

        $recommendation = $reelBetter
            ? 'Le regime reel semble plus avantageux dans votre situation.'
            : 'Le regime micro-entrepreneur semble plus avantageux dans votre situation.';

        $absSavings = $reelBetter ? $savings : bcmul($savings, '-1', 2);
        $betterRegime = $reelBetter ? 'reel' : 'micro';
        $details = sprintf(
            'Le regime %s vous ferait economiser %s EUR par an par rapport a l\'autre regime.',
            $betterRegime,
            $absSavings,
        );

        return [
            'micro' => [
                'turnover' => $turnover,
                'abatement' => $abatement,
                'taxableIncome' => $microTaxable,
                'cotisations' => $microCotisations,
                'netIncome' => $microNet,
            ],
            'reel' => [
                'turnover' => $turnover,
                'expenses' => $actualExpenses,
                'taxableIncome' => $reelTaxable,
                'cotisations' => $reelCotisations,
                'netIncome' => $reelNet,
            ],
            'recommendation' => $recommendation,
            'savings' => $absSavings,
            'details' => $details,
        ];
    }

    /**
     * Simule la comparaison EI vs societe (EURL IS ou SASU).
     *
     * @param string $turnover CA annuel HT
     * @param string $expenses Charges reelles annuelles (hors remuneration)
     * @param string $salary   Remuneration souhaitee du dirigeant
     *
     * @return array{
     *     ei: array{turnover: string, benefice: string, cotisations: string, ir: string, netAfterTax: string},
     *     societe: array{turnover: string, benefice: string, is: string, salary: string, cotisationsSalary: string, dividendes: string, flatTax: string, netAfterTax: string},
     *     recommendation: string,
     *     savings: string
     * }
     */
    public function simulateEiVsSociete(string $turnover, string $expenses, string $salary): array
    {
        /** @var numeric-string $turnoverNum */
        $turnoverNum = $turnover;
        /** @var numeric-string $expensesNum */
        $expensesNum = $expenses;
        /** @var numeric-string $salaryNum */
        $salaryNum = $salary;

        // --- EI (regime reel) ---
        /** @var numeric-string $eiBenefice */
        $eiBenefice = bcsub($turnoverNum, $expensesNum, 2);
        if (bccomp($eiBenefice, '0', 2) < 0) {
            $eiBenefice = '0.00';
        }
        // Cotisations sociales TNS : ~45% du benefice
        /** @var numeric-string $eiCotisations */
        $eiCotisations = bcmul($eiBenefice, '0.45', 2);
        /** @var numeric-string $eiTaxable */
        $eiTaxable = bcsub($eiBenefice, $eiCotisations, 2);
        /** @var numeric-string $eiIr */
        $eiIr = $this->calculateIncomeTax($eiTaxable);
        /** @var numeric-string $eiNet */
        $eiNet = bcsub($eiTaxable, $eiIr, 2);

        // --- Societe (EURL IS ou SASU) ---
        /** @var numeric-string $societeCharges */
        $societeCharges = bcadd($expensesNum, $salaryNum, 2);
        // Cotisations patronales sur la remuneration (~55% en SASU)
        /** @var numeric-string $cotisationsSalary */
        $cotisationsSalary = bcmul($salaryNum, '0.55', 2);
        /** @var numeric-string $totalCharges */
        $totalCharges = bcadd($societeCharges, $cotisationsSalary, 2);
        /** @var numeric-string $societeBenefice */
        $societeBenefice = bcsub($turnoverNum, $totalCharges, 2);
        if (bccomp($societeBenefice, '0', 2) < 0) {
            $societeBenefice = '0.00';
        }

        // IS : 15% jusqu'a 42 500 EUR, 25% au-dela
        /** @var numeric-string $is */
        $is = $this->calculateCorporateTax($societeBenefice);
        /** @var numeric-string $afterIs */
        $afterIs = bcsub($societeBenefice, $is, 2);

        // Dividendes : ce qui reste apres IS
        $dividendes = $afterIs;
        // Flat tax 30% sur les dividendes
        /** @var numeric-string $flatTax */
        $flatTax = bcmul($dividendes, '0.30', 2);
        /** @var numeric-string $netDividendes */
        $netDividendes = bcsub($dividendes, $flatTax, 2);
        /** @var numeric-string $societeNet */
        $societeNet = bcadd($salaryNum, $netDividendes, 2);

        // Comparaison
        /** @var numeric-string $savings */
        $savings = bcsub($societeNet, $eiNet, 2);
        $societeBetter = bccomp($savings, '0', 2) > 0;

        $recommendation = $societeBetter
            ? 'Le passage en societe semble avantageux dans votre situation.'
            : 'Rester en entreprise individuelle semble plus avantageux dans votre situation.';

        $absSavings = $societeBetter ? $savings : bcmul($savings, '-1', 2);

        return [
            'ei' => [
                'turnover' => $turnover,
                'benefice' => $eiBenefice,
                'cotisations' => $eiCotisations,
                'ir' => $eiIr,
                'netAfterTax' => $eiNet,
            ],
            'societe' => [
                'turnover' => $turnover,
                'benefice' => $societeBenefice,
                'is' => $is,
                'salary' => $salary,
                'cotisationsSalary' => $cotisationsSalary,
                'dividendes' => $dividendes,
                'flatTax' => $flatTax,
                'netAfterTax' => $societeNet,
            ],
            'recommendation' => $recommendation,
            'savings' => $absSavings,
        ];
    }

    /**
     * Estime l'impot sur le revenu selon le bareme progressif.
     *
     * @param string $taxableIncome Revenu imposable annuel
     * @param int    $parts         Nombre de parts de quotient familial
     *
     * @return array{
     *     taxableIncome: string,
     *     parts: int,
     *     quotient: string,
     *     taxBeforeCap: string,
     *     tax: string,
     *     marginalRate: string,
     *     effectiveRate: string,
     *     breakdown: list<array{tranche: string, rate: string, amount: string}>
     * }
     */
    public function estimateIncomeTax(string $taxableIncome, int $parts = 1): array
    {
        /** @var numeric-string $income */
        $income = $taxableIncome;
        $partsStr = (string) $parts;

        // Quotient familial
        /** @var numeric-string $quotient */
        $quotient = bcdiv($income, $partsStr, 2);

        // Calcul par tranche sur le quotient
        $tranches = [
            ['max' => '11294', 'rate' => '0'],
            ['max' => '28797', 'rate' => '11'],
            ['max' => '82341', 'rate' => '30'],
            ['max' => '177106', 'rate' => '41'],
            ['max' => null, 'rate' => '45'],
        ];

        /** @var numeric-string $taxPerPart */
        $taxPerPart = '0.00';
        /** @var numeric-string $previousMax */
        $previousMax = '0';
        $marginalRate = '0';
        $breakdown = [];

        foreach ($tranches as $tranche) {
            if (bccomp($quotient, $previousMax, 2) <= 0) {
                break;
            }

            /** @var numeric-string $upper */
            $upper = $tranche['max'] ?? $quotient;
            if (bccomp($quotient, $upper, 2) < 0) {
                $upper = $quotient;
            }

            /** @var numeric-string $trancheAmount */
            $trancheAmount = bcsub($upper, $previousMax, 2);
            /** @var numeric-string $rate */
            $rate = $tranche['rate'];
            /** @var numeric-string $trancheTax */
            $trancheTax = bcmul($trancheAmount, bcdiv($rate, '100', 4), 2);

            $taxPerPart = bcadd($taxPerPart, $trancheTax, 2);
            $marginalRate = $rate;

            $breakdown[] = [
                'tranche' => sprintf('%s - %s EUR', $previousMax, $tranche['max'] ?? '...'),
                'rate' => $rate . '%',
                'amount' => $trancheTax,
            ];

            $previousMax = $tranche['max'] ?? $quotient;
        }

        // Impot total = impot par part * nombre de parts
        /** @var numeric-string $totalTax */
        $totalTax = bcmul($taxPerPart, $partsStr, 2);

        // Taux effectif
        $effectiveRate = '0.00';
        if (bccomp($income, '0', 2) > 0) {
            /** @var numeric-string $effectiveRate */
            $effectiveRate = bcmul(bcdiv($totalTax, $income, 4), '100', 2);
        }

        return [
            'taxableIncome' => $taxableIncome,
            'parts' => $parts,
            'quotient' => $quotient,
            'taxBeforeCap' => $totalTax,
            'tax' => $totalTax,
            'marginalRate' => $marginalRate . '%',
            'effectiveRate' => $effectiveRate . '%',
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calcule l'IS selon le bareme PME.
     */
    public function calculateCorporateTax(string $benefice): string
    {
        /** @var numeric-string $beneficeNum */
        $beneficeNum = $benefice;

        if (bccomp($beneficeNum, self::IS_REDUCED_CEILING, 2) <= 0) {
            // Tout au taux reduit de 15%
            return bcmul($beneficeNum, '0.15', 2);
        }

        // 15% sur les 42 500 premiers euros
        /** @var numeric-string $taxReduced */
        $taxReduced = bcmul(self::IS_REDUCED_CEILING, '0.15', 2);
        // 25% sur le reste
        /** @var numeric-string $excess */
        $excess = bcsub($beneficeNum, self::IS_REDUCED_CEILING, 2);
        /** @var numeric-string $taxNormal */
        $taxNormal = bcmul($excess, '0.25', 2);

        return bcadd($taxReduced, $taxNormal, 2);
    }

    /**
     * Calcule l'IR par le bareme progressif (version simplifiee 1 part).
     */
    private function calculateIncomeTax(string $taxableIncome): string
    {
        $result = $this->estimateIncomeTax($taxableIncome, 1);

        return $result['tax'];
    }

    /**
     * Retourne le taux d'abattement micro selon l'activite.
     */
    private function getMicroAbatementRate(string $activityType): string
    {
        return match ($activityType) {
            'bic_sale' => '71',
            'bic_service' => '50',
            default => '34',
        };
    }

    /**
     * Retourne le taux de cotisations URSSAF selon l'activite.
     */
    private function getUrssafRate(string $activityType): string
    {
        return match ($activityType) {
            'bic_sale' => '12.3',
            'bic_service' => '21.2',
            'bnc_liberal' => '21.1',
            default => '21.2',
        };
    }
}
