<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Exception\InvoiceValidationException;

/**
 * Valide qu'une facture respecte toutes les exigences du Decret 2022-1299.
 *
 * Cette validation est executee avant chaque emission (transition DRAFT -> SENT)
 * pour garantir la conformite DGFiP. Elle verifie la presence de toutes les
 * donnees obligatoires : vendeur, acheteur, lignes, totaux, mentions legales.
 */
class InvoiceValidator
{
    /**
     * Valide une facture et retourne la liste des erreurs.
     *
     * @return list<string> Liste des messages d'erreur (vide si la facture est valide)
     */
    public function validate(Invoice $invoice): array
    {
        $errors = [];

        // Vendeur obligatoire avec donnees completes
        $this->validateSeller($invoice, $errors);

        // Acheteur obligatoire avec donnees completes
        $this->validateBuyer($invoice, $errors);

        // En-tete de la facture
        $this->validateHeader($invoice, $errors);

        // Au moins une ligne de facture
        $this->validateLines($invoice, $errors);

        // Coherence des totaux
        $this->validateTotals($invoice, $errors);

        // Mentions legales obligatoires
        $this->validateLegalMentions($invoice, $errors);

        return $errors;
    }

    /**
     * Valide et leve une exception si la facture est invalide.
     *
     * @throws InvoiceValidationException Si la facture ne respecte pas les exigences
     */
    public function assertValid(Invoice $invoice): void
    {
        $errors = $this->validate($invoice);

        if (\count($errors) > 0) {
            throw new InvoiceValidationException($errors);
        }
    }

    /**
     * Verifie les donnees obligatoires du vendeur.
     *
     * @param list<string> $errors
     */
    private function validateSeller(Invoice $invoice, array &$errors): void
    {
        $seller = $invoice->getSeller();

        if (empty($seller->getName())) {
            $errors[] = 'La raison sociale du vendeur est obligatoire.';
        }

        if (empty($seller->getSiren())) {
            $errors[] = 'Le SIREN du vendeur est obligatoire.';
        }

        if (empty($seller->getAddressLine1())) {
            $errors[] = "L'adresse du vendeur est obligatoire.";
        }

        if (empty($seller->getPostalCode())) {
            $errors[] = 'Le code postal du vendeur est obligatoire.';
        }

        if (empty($seller->getCity())) {
            $errors[] = 'La ville du vendeur est obligatoire.';
        }

        if (empty($seller->getLegalForm())) {
            $errors[] = 'La forme juridique du vendeur est obligatoire.';
        }
    }

    /**
     * Verifie les donnees obligatoires de l'acheteur.
     *
     * @param list<string> $errors
     */
    private function validateBuyer(Invoice $invoice, array &$errors): void
    {
        $buyer = $invoice->getBuyer();

        if (empty($buyer->getName())) {
            $errors[] = "La raison sociale de l'acheteur est obligatoire.";
        }

        if (empty($buyer->getAddressLine1())) {
            $errors[] = "L'adresse de l'acheteur est obligatoire.";
        }

        if (empty($buyer->getPostalCode())) {
            $errors[] = "Le code postal de l'acheteur est obligatoire.";
        }

        if (empty($buyer->getCity())) {
            $errors[] = "La ville de l'acheteur est obligatoire.";
        }
    }

    /**
     * Verifie l'en-tete de la facture.
     *
     * @param list<string> $errors
     */
    private function validateHeader(Invoice $invoice, array &$errors): void
    {
        if ('EUR' !== $invoice->getCurrency()) {
            $errors[] = 'La devise doit etre EUR pour les factures francaises.';
        }
    }

    /**
     * Verifie les lignes de la facture.
     *
     * @param list<string> $errors
     */
    private function validateLines(Invoice $invoice, array &$errors): void
    {
        if ($invoice->getLines()->isEmpty()) {
            $errors[] = 'La facture doit contenir au moins une ligne.';

            return;
        }

        foreach ($invoice->getLines() as $index => $line) {
            $lineNumber = $index + 1;

            if (empty($line->getDescription())) {
                $errors[] = sprintf('Ligne %d : la designation est obligatoire.', $lineNumber);
            }

            if (!is_numeric($line->getQuantity()) || bccomp($line->getQuantity(), '0', 4) <= 0) {
                $errors[] = sprintf('Ligne %d : la quantite doit etre strictement positive.', $lineNumber);
            }

            if (!is_numeric($line->getUnitPriceExcludingTax())) {
                $errors[] = sprintf('Ligne %d : le prix unitaire HT est obligatoire.', $lineNumber);
            }

            // Le taux de TVA doit etre un nombre ou un code special (AE, E, Z)
            $validCodes = ['AE', 'E', 'Z'];
            if (!is_numeric($line->getVatRate()) && !\in_array($line->getVatRate(), $validCodes, true)) {
                $errors[] = sprintf('Ligne %d : le taux de TVA est invalide (%s).', $lineNumber, $line->getVatRate());
            }
        }
    }

    /**
     * Verifie la coherence des totaux.
     *
     * @param list<string> $errors
     */
    private function validateTotals(Invoice $invoice, array &$errors): void
    {
        // Recalculer les totaux attendus
        /** @var numeric-string $expectedHt */
        $expectedHt = '0.00';
        /** @var numeric-string $expectedTax */
        $expectedTax = '0.00';

        foreach ($invoice->getLines() as $line) {
            $lineAmount = $line->getLineAmount();
            $vatAmount = $line->getVatAmount();
            \assert(is_numeric($lineAmount));
            \assert(is_numeric($vatAmount));

            $expectedHt = bcadd($expectedHt, $lineAmount, 2);
            $expectedTax = bcadd($expectedTax, $vatAmount, 2);
        }

        $expectedTtc = bcadd($expectedHt, $expectedTax, 2);

        $totalHt = $invoice->getTotalExcludingTax();
        $totalTax = $invoice->getTotalTax();
        $totalTtc = $invoice->getTotalIncludingTax();
        \assert(is_numeric($totalHt));
        \assert(is_numeric($totalTax));
        \assert(is_numeric($totalTtc));

        if (0 !== bccomp($totalHt, $expectedHt, 2)) {
            $errors[] = sprintf(
                'Le total HT (%s) ne correspond pas a la somme des lignes (%s).',
                $totalHt,
                $expectedHt,
            );
        }

        if (0 !== bccomp($totalTax, $expectedTax, 2)) {
            $errors[] = sprintf(
                'Le total TVA (%s) ne correspond pas a la somme des lignes (%s).',
                $totalTax,
                $expectedTax,
            );
        }

        if (0 !== bccomp($totalTtc, $expectedTtc, 2)) {
            $errors[] = sprintf(
                'Le total TTC (%s) ne correspond pas au calcul HT + TVA (%s).',
                $totalTtc,
                $expectedTtc,
            );
        }
    }

    /**
     * Verifie les mentions legales obligatoires.
     *
     * Certaines situations fiscales exigent une mention specifique :
     * - Exoneration : reference a l'article du CGI
     * - Art. 293 B : mention obligatoire pour les micro-entrepreneurs
     * - Autoliquidation : mention obligatoire pour les operations B2B intra-UE
     *
     * @param list<string> $errors
     */
    private function validateLegalMentions(Invoice $invoice, array &$errors): void
    {
        $hasExemptLine = false;
        $hasReverseChargeLine = false;

        foreach ($invoice->getLines() as $line) {
            if ('E' === $line->getVatRate()) {
                $hasExemptLine = true;
            }
            if ('AE' === $line->getVatRate()) {
                $hasReverseChargeLine = true;
            }
        }

        if ($hasExemptLine && empty($invoice->getLegalMention())) {
            $errors[] = "Une mention legale d'exoneration TVA est obligatoire (ex: TVA non applicable — art. 293 B du CGI).";
        }

        if ($hasReverseChargeLine && (empty($invoice->getLegalMention()) || !str_contains($invoice->getLegalMention(), 'autoliquidation'))) {
            $errors[] = "La mention 'Autoliquidation' est obligatoire pour les operations en autoliquidation.";
        }
    }
}
