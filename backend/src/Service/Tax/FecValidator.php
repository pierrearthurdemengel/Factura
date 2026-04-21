<?php

namespace App\Service\Tax;

/**
 * Valide un fichier FEC genere selon les controles de coherence obligatoires.
 *
 * Controles effectues :
 * - Presence des 18 colonnes obligatoires
 * - Equilibre debit/credit par ecriture
 * - Format des dates (AAAAMMJJ)
 * - Format des montants (numeriques)
 * - Presence du numero d'ecriture
 * - Tri chronologique des ecritures
 */
class FecValidator
{
    /** @var string[] */
    private array $errors = [];

    /**
     * Valide le contenu d'un fichier FEC.
     *
     * @return array{valid: bool, errors: string[], lineCount: int, entryCount: int, totalDebit: string, totalCredit: string}
     */
    public function validate(string $fecContent): array
    {
        $this->errors = [];

        // Retirer uniquement les sauts de ligne finaux, pas les tabulations
        $lines = explode("\n", rtrim($fecContent, "\n\r"));
        if (\count($lines) < 2) {
            $this->errors[] = 'Le FEC doit contenir au moins une ligne d\'en-tete et une ecriture.';

            return $this->buildResult($lines);
        }

        // Verifier l'en-tete (18 colonnes)
        $header = explode("\t", $lines[0]);
        $this->validateHeader($header);

        // Verifier chaque ligne de donnees
        /** @var numeric-string $totalDebit */
        $totalDebit = '0.00';
        /** @var numeric-string $totalCredit */
        $totalCredit = '0.00';
        $entryNums = [];
        $previousDate = '';

        for ($i = 1, $count = \count($lines); $i < $count; ++$i) {
            $line = $lines[$i];
            if ('' === trim($line)) {
                continue;
            }

            $fields = explode("\t", $line);
            $lineNum = $i + 1;

            // Verifier le nombre de colonnes
            if (18 !== \count($fields)) {
                $this->errors[] = sprintf(
                    'Ligne %d : %d colonnes au lieu de 18.',
                    $lineNum,
                    \count($fields),
                );
                continue;
            }

            $previousDate = $this->validateLineFields($fields, $lineNum, $previousDate);
            $entryNums[$fields[2]] = true;

            [$totalDebit, $totalCredit] = $this->accumulateAmounts($fields, $lineNum, $totalDebit, $totalCredit);
        }

        // Verifier l'equilibre global debit/credit
        if (0 !== bccomp($totalDebit, $totalCredit, 2)) {
            $this->errors[] = sprintf(
                'Desequilibre global : debit %s != credit %s.',
                $totalDebit,
                $totalCredit,
            );
        }

        $lineCount = \count($lines) - 1; // Moins l'en-tete
        $entryCount = \count($entryNums);

        return [
            'valid' => 0 === \count($this->errors),
            'errors' => $this->errors,
            'lineCount' => $lineCount,
            'entryCount' => $entryCount,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
        ];
    }

    /**
     * Valide les champs d'une ligne FEC (date, numero d'ecriture, compte).
     *
     * @param string[] $fields
     *
     * @return string La date de cette ligne (pour le controle chronologique)
     */
    private function validateLineFields(array $fields, int $lineNum, string $previousDate): string
    {
        // Verifier le format de date (AAAAMMJJ)
        $date = $fields[3];
        if (!$this->isValidDate($date)) {
            $this->errors[] = sprintf('Ligne %d : date invalide "%s".', $lineNum, $date);
        }

        // Verifier le tri chronologique
        if ('' !== $previousDate && $date < $previousDate) {
            $this->errors[] = sprintf(
                'Ligne %d : date %s anterieure a la date precedente %s (tri chronologique requis).',
                $lineNum,
                $date,
                $previousDate,
            );
        }

        // Verifier le numero d'ecriture
        if ('' === $fields[2]) {
            $this->errors[] = sprintf('Ligne %d : numero d\'ecriture manquant.', $lineNum);
        }

        // Verifier que le compte est renseigne
        if ('' === trim($fields[4])) {
            $this->errors[] = sprintf('Ligne %d : numero de compte manquant.', $lineNum);
        }

        return $date;
    }

    /**
     * Accumule les montants debit/credit d'une ligne FEC.
     *
     * @param string[]       $fields
     * @param numeric-string $totalDebit
     * @param numeric-string $totalCredit
     *
     * @return array{numeric-string, numeric-string}
     */
    private function accumulateAmounts(array $fields, int $lineNum, string $totalDebit, string $totalCredit): array
    {
        $debit = $this->parseAmount($fields[11]);
        $credit = $this->parseAmount($fields[12]);

        if (null === $debit) {
            $this->errors[] = sprintf('Ligne %d : montant debit invalide "%s".', $lineNum, $fields[11]);
        } else {
            /** @var numeric-string $debit */
            $totalDebit = bcadd($totalDebit, $debit, 2);
        }

        if (null === $credit) {
            $this->errors[] = sprintf('Ligne %d : montant credit invalide "%s".', $lineNum, $fields[12]);
        } else {
            /** @var numeric-string $credit */
            $totalCredit = bcadd($totalCredit, $credit, 2);
        }

        return [$totalDebit, $totalCredit];
    }

    /**
     * Verifie que l'en-tete contient les 18 colonnes obligatoires.
     *
     * @param string[] $header
     */
    private function validateHeader(array $header): void
    {
        if (18 !== \count($header)) {
            $this->errors[] = sprintf(
                'En-tete : %d colonnes au lieu de 18.',
                \count($header),
            );

            return;
        }

        $expected = FecExporter::COLUMNS;
        foreach ($expected as $i => $colName) {
            if (!isset($header[$i]) || trim($header[$i]) !== $colName) {
                $this->errors[] = sprintf(
                    'En-tete colonne %d : attendu "%s", trouve "%s".',
                    $i + 1,
                    $colName,
                    $header[$i] ?? '(manquant)',
                );
            }
        }
    }

    /**
     * Verifie le format d'une date FEC (AAAAMMJJ).
     */
    private function isValidDate(string $date): bool
    {
        if ('' === $date) {
            return true; // Certaines dates sont optionnelles
        }

        if (8 !== \strlen($date)) {
            return false;
        }

        $year = (int) substr($date, 0, 4);
        $month = (int) substr($date, 4, 2);
        $day = (int) substr($date, 6, 2);

        return checkdate($month, $day, $year);
    }

    /**
     * Parse un montant FEC (format francais avec virgule).
     *
     * @return string|null Le montant en notation standard ou null si invalide
     */
    private function parseAmount(string $amount): ?string
    {
        // Remplacer la virgule par un point
        $normalized = str_replace(',', '.', trim($amount));

        if (!is_numeric($normalized)) {
            return null;
        }

        return $normalized;
    }

    /**
     * Construit le resultat de validation.
     *
     * @param string[] $lines
     *
     * @return array{valid: bool, errors: string[], lineCount: int, entryCount: int, totalDebit: string, totalCredit: string}
     */
    private function buildResult(array $lines): array
    {
        return [
            'valid' => 0 === \count($this->errors),
            'errors' => $this->errors,
            'lineCount' => max(0, \count($lines) - 1),
            'entryCount' => 0,
            'totalDebit' => '0.00',
            'totalCredit' => '0.00',
        ];
    }
}
