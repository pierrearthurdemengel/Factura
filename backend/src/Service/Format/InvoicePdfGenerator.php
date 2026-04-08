<?php

namespace App\Service\Format;

use App\Entity\Invoice;

/**
 * Genere un PDF de mise en page pour une facture.
 *
 * Ce PDF sert de template visuel qui sera ensuite fusionne
 * avec le XML CII D16B via ZugferdDocumentPdfBuilder pour
 * produire un Factur-X conforme PDF/A-3.
 *
 * Supporte la personnalisation : logo, couleur primaire, pied de page.
 */
class InvoicePdfGenerator
{
    /**
     * Genere le contenu PDF (print layout) d'une facture.
     *
     * @return string Le contenu PDF brut
     */
    public function generate(Invoice $invoice): string
    {
        $seller = $invoice->getSeller();
        $buyer = $invoice->getBuyer();

        // Couleur primaire personnalisee ou bleu par defaut
        $primaryColor = $this->hexToRgb($seller->getPrimaryColor() ?? '#2563EB');

        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 25);

        // Logo de l'entreprise (si configure)
        $logoPath = $seller->getLogoPath();
        $logoHeight = 0;
        if (null !== $logoPath && file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 40);
            $logoHeight = 25;
        }

        // En-tete : vendeur
        $startY = 10 + $logoHeight;
        $pdf->SetY($startY);
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->Cell(0, 8, $this->encode($seller->getName()), 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 5, $this->encode($seller->getAddressLine1()), 0, 1);
        $pdf->Cell(0, 5, $this->encode($seller->getPostalCode() . ' ' . $seller->getCity()), 0, 1);
        $pdf->Cell(0, 5, 'SIREN : ' . $seller->getSiren(), 0, 1);
        if (null !== $seller->getVatNumber()) {
            $pdf->Cell(0, 5, 'TVA : ' . $seller->getVatNumber(), 0, 1);
        }

        $pdf->Ln(10);

        // Titre facture avec couleur primaire
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $number = $invoice->getNumber() ?? 'BROUILLON';
        $pdf->Cell(0, 10, 'FACTURE ' . $number, 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, 'Date : ' . $invoice->getIssueDate()->format('d/m/Y'), 0, 1);
        if (null !== $invoice->getDueDate()) {
            $pdf->Cell(0, 5, $this->encode('Echeance : ') . $invoice->getDueDate()->format('d/m/Y'), 0, 1);
        }

        $pdf->Ln(5);

        // Client
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Client :', 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, $this->encode($buyer->getName()), 0, 1);
        $pdf->Cell(0, 5, $this->encode($buyer->getAddressLine1()), 0, 1);
        $pdf->Cell(0, 5, $this->encode($buyer->getPostalCode() . ' ' . $buyer->getCity()), 0, 1);
        if (null !== $buyer->getSiren()) {
            $pdf->Cell(0, 5, 'SIREN : ' . $buyer->getSiren(), 0, 1);
        }

        $pdf->Ln(8);

        // Tableau des lignes avec en-tete en couleur primaire
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(70, 7, 'Description', 1, 0, 'L', true);
        $pdf->Cell(20, 7, $this->encode('Qte'), 1, 0, 'R', true);
        $pdf->Cell(15, 7, $this->encode('Unite'), 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Prix HT', 1, 0, 'R', true);
        $pdf->Cell(20, 7, 'TVA %', 1, 0, 'R', true);
        $pdf->Cell(30, 7, 'Total HT', 1, 1, 'R', true);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 9);
        foreach ($invoice->getLines() as $line) {
            $pdf->Cell(70, 6, $this->encode($this->truncate($line->getDescription(), 40)), 1, 0);
            $pdf->Cell(20, 6, $line->getQuantity(), 1, 0, 'R');
            $pdf->Cell(15, 6, $line->getUnit(), 1, 0, 'C');
            $pdf->Cell(25, 6, $line->getUnitPriceExcludingTax() . ' EUR', 1, 0, 'R');
            $pdf->Cell(20, 6, $line->getVatRate() . '%', 1, 0, 'R');
            $pdf->Cell(30, 6, $line->getLineAmount() . ' EUR', 1, 1, 'R');
        }

        $pdf->Ln(5);

        // Totaux
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(150, 6, 'Total HT :', 0, 0, 'R');
        $pdf->Cell(30, 6, $invoice->getTotalExcludingTax() . ' EUR', 0, 1, 'R');
        $pdf->Cell(150, 6, 'Total TVA :', 0, 0, 'R');
        $pdf->Cell(30, 6, $invoice->getTotalTax() . ' EUR', 0, 1, 'R');
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->Cell(150, 7, 'Total TTC :', 0, 0, 'R');
        $pdf->Cell(30, 7, $invoice->getTotalIncludingTax() . ' EUR', 0, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);

        // Mention legale
        if (null !== $invoice->getLegalMention()) {
            $pdf->Ln(8);
            $pdf->SetFont('Helvetica', 'I', 8);
            $pdf->MultiCell(0, 4, $this->encode($invoice->getLegalMention()));
        }

        // Conditions de paiement
        if (null !== $invoice->getPaymentTerms()) {
            $pdf->Ln(3);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->MultiCell(0, 4, $this->encode($invoice->getPaymentTerms()));
        }

        // Mentions legales automatiques selon forme juridique
        $legalAutoMention = $this->getAutoLegalMention($seller->getLegalForm(), $seller);
        if (null !== $legalAutoMention) {
            $pdf->Ln(3);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->MultiCell(0, 3, $this->encode($legalAutoMention));
        }

        // Pied de page personnalise
        $customFooter = $seller->getCustomFooter();
        if (null !== $customFooter && '' !== $customFooter) {
            $pdf->Ln(3);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->MultiCell(0, 3, $this->encode($customFooter));
        }

        return $pdf->Output('S');
    }

    /**
     * Genere les mentions legales automatiques selon la forme juridique.
     */
    private function getAutoLegalMention(string $legalForm, \App\Entity\Company $company): ?string
    {
        $mentions = [];

        $form = strtoupper($legalForm);

        if (str_contains($form, 'MICRO') || str_contains($form, 'AUTO')) {
            $mentions[] = 'TVA non applicable, art. 293 B du CGI';
        }

        if (str_contains($form, 'EI') || str_contains($form, 'EIRL')) {
            $mentions[] = 'Entrepreneur individuel';
        }

        if (str_contains($form, 'SAS') || str_contains($form, 'SARL') || str_contains($form, 'SA')) {
            $name = $company->getName();
            $siren = $company->getSiren();
            $city = $company->getCity();
            $mentions[] = sprintf('%s - %s - SIREN %s - %s', $name, $form, $siren, $city);
        }

        return [] !== $mentions ? implode(' - ', $mentions) : null;
    }

    /**
     * Convertit une couleur hexadecimale en tableau RGB.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (6 !== strlen($hex)) {
            return [37, 99, 235]; // Bleu par defaut
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Encode les caracteres UTF-8 en ISO-8859-1 pour FPDF.
     */
    private function encode(string $text): string
    {
        $result = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

        return false === $result ? $text : $result;
    }

    /**
     * Tronque une chaine a la longueur donnee.
     */
    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3) . '...';
    }
}
