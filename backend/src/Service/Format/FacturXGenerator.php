<?php

namespace App\Service\Format;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;

/**
 * Genere un fichier XML CII D16B conforme au profil EN 16931 (Factur-X).
 *
 * Le XML genere peut etre embarque dans un PDF/A-3 pour produire
 * un document Factur-X complet. Le profil EN 16931 est le minimum
 * requis par la reforme DGFiP pour la facturation electronique.
 */
class FacturXGenerator
{
    /**
     * Genere le XML CII D16B a partir d'une entite Invoice.
     *
     * @return string Le contenu XML conforme EN 16931
     */
    public function generate(Invoice $invoice): string
    {
        return $this->buildDocument($invoice)->getContent();
    }

    /**
     * Construit et retourne le ZugferdDocumentBuilder pour une facture.
     * Utilise par le generateur PDF pour obtenir le builder necessaire
     * a la creation du Factur-X PDF/A-3.
     */
    public function buildDocument(Invoice $invoice): ZugferdDocumentBuilder
    {
        $doc = ZugferdDocumentBuilder::CreateNew(ZugferdProfiles::PROFILE_EN16931);

        // En-tete du document
        $doc->setDocumentInformation(
            $invoice->getNumber() ?? '',
            '380', // 380 = facture commerciale
            \DateTime::createFromImmutable($invoice->getIssueDate()),
            $invoice->getCurrency(),
        );

        // Date de livraison
        $deliveryDate = $invoice->getDeliveryDate() ?? $invoice->getIssueDate();
        $doc->setDocumentSupplyChainEvent(
            \DateTime::createFromImmutable($deliveryDate)
        );

        // Vendeur
        $seller = $invoice->getSeller();
        if (null === $seller) {
            throw new \RuntimeException('La facture doit avoir un vendeur pour generer le Factur-X.');
        }
        $doc->setDocumentSeller($seller->getName());
        $doc->setDocumentSellerAddress(
            $seller->getAddressLine1(),
            '',
            '',
            $seller->getPostalCode(),
            $seller->getCity(),
            $seller->getCountryCode(),
        );

        // Organisation legale du vendeur (SIREN)
        $doc->addDocumentSellerTaxRegistration('FC', $seller->getSiren());

        // TVA intracommunautaire du vendeur
        if (null !== $seller->getVatNumber()) {
            $doc->addDocumentSellerTaxRegistration('VA', $seller->getVatNumber());
        }

        // Acheteur
        $buyer = $invoice->getBuyer();
        $doc->setDocumentBuyer($buyer->getName());
        $doc->setDocumentBuyerAddress(
            $buyer->getAddressLine1(),
            '',
            '',
            $buyer->getPostalCode(),
            $buyer->getCity(),
            $buyer->getCountryCode(),
        );

        // TVA intracommunautaire de l'acheteur
        if (null !== $buyer->getVatNumber()) {
            $doc->addDocumentBuyerTaxRegistration('VA', $buyer->getVatNumber());
        }

        // Conditions de paiement
        if (null !== $invoice->getPaymentTerms()) {
            $doc->addDocumentPaymentTerm($invoice->getPaymentTerms());
        }

        if (null !== $invoice->getDueDate()) {
            $doc->addDocumentPaymentTerm(
                null,
                \DateTime::createFromImmutable($invoice->getDueDate()),
            );
        }

        // Mention legale
        if (null !== $invoice->getLegalMention()) {
            $doc->addDocumentNote($invoice->getLegalMention(), null, 'AAK');
        }

        // Lignes de facture
        foreach ($invoice->getLines() as $line) {
            $this->addLine($doc, $line);
        }

        // Totaux TVA par taux
        $taxGroups = $this->groupByVatRate($invoice);
        foreach ($taxGroups as $rate => $amounts) {
            $categoryCode = $this->getTaxCategoryCode($rate, $invoice->getLegalMention());
            $exemptionReason = ('E' === $categoryCode && null !== $invoice->getLegalMention())
                ? $invoice->getLegalMention()
                : null;

            $doc->addDocumentTax(
                $categoryCode,
                'VAT',
                (float) $amounts['basis'],
                (float) $amounts['tax'],
                (float) $rate,
                $exemptionReason,
            );
        }

        // Totaux globaux
        $doc->setDocumentSummation(
            (float) $invoice->getTotalIncludingTax(),
            (float) $invoice->getTotalIncludingTax(),
            (float) $invoice->getTotalExcludingTax(),
            0.0,
            0.0,
            (float) $invoice->getTotalExcludingTax(),
            (float) $invoice->getTotalTax(),
        );

        return $doc;
    }

    /**
     * Ajoute une ligne de facture au document CII.
     */
    private function addLine(ZugferdDocumentBuilder $doc, InvoiceLine $line): void
    {
        $doc->addNewPosition((string) $line->getPosition());
        $doc->setDocumentPositionProductDetails($line->getDescription());
        $doc->setDocumentPositionQuantity(
            (float) $line->getQuantity(),
            $line->getUnit(),
        );
        $doc->setDocumentPositionNetPrice((float) $line->getUnitPriceExcludingTax());
        $doc->setDocumentPositionLineSummation((float) $line->getLineAmount());

        $categoryCode = $this->getTaxCategoryCode($line->getVatRate(), null);
        $rate = is_numeric($line->getVatRate()) ? (float) $line->getVatRate() : 0.0;
        $doc->addDocumentPositionTax($categoryCode, 'VAT', $rate);
    }

    /**
     * Determine le code categorie TVA selon le taux et la mention legale.
     *
     * S = Standard, Z = Zero, E = Exonere, AE = Autoliquidation
     */
    private function getTaxCategoryCode(string $vatRate, ?string $legalMention): string
    {
        if ('AE' === $vatRate || (null !== $legalMention && str_contains($legalMention, 'autoliquidation'))) {
            return 'AE';
        }

        if ('E' === $vatRate || (null !== $legalMention && str_contains($legalMention, '293 B'))) {
            return 'E';
        }

        if ('Z' === $vatRate || '0' === $vatRate || '0.00' === $vatRate) {
            return 'Z';
        }

        return 'S';
    }

    /**
     * Regroupe les lignes par taux de TVA pour les totaux.
     *
     * @return array<string, array{basis: numeric-string, tax: numeric-string}>
     */
    private function groupByVatRate(Invoice $invoice): array
    {
        /** @var array<string, array{basis: numeric-string, tax: numeric-string}> $groups */
        $groups = [];

        foreach ($invoice->getLines() as $line) {
            $rate = $line->getVatRate();
            // Forcer la cle en string avec un format decimal pour eviter le cast int de PHP
            $key = is_numeric($rate) ? number_format((float) $rate, 2, '.', '') : '0.00';

            if (!isset($groups[$key])) {
                $groups[$key] = ['basis' => '0.00', 'tax' => '0.00'];
            }

            $lineAmount = $line->getLineAmount();
            $vatAmount = $line->getVatAmount();
            \assert(is_numeric($lineAmount));
            \assert(is_numeric($vatAmount));

            $groups[$key]['basis'] = bcadd($groups[$key]['basis'], $lineAmount, 2);
            $groups[$key]['tax'] = bcadd($groups[$key]['tax'], $vatAmount, 2);
        }

        /** @var array<string, array{basis: numeric-string, tax: numeric-string}> */
        return $groups;
    }
}
