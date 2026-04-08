<?php

namespace App\Service\Reminder;

use App\Entity\Invoice;

/**
 * Genere un PDF de mise en demeure conforme au droit francais.
 *
 * Le document inclut les references legales (articles L.441-10 et L.441-6
 * du Code de commerce), les coordonnees des parties, le detail de la creance
 * et un delai de paiement de 8 jours.
 */
class FormalNoticePdfGenerator
{
    /**
     * Genere le contenu PDF d'une mise en demeure pour une facture impayee.
     *
     * @return string Le contenu PDF brut
     */
    public function generate(Invoice $invoice): string
    {
        $seller = $invoice->getSeller();
        $buyer = $invoice->getBuyer();
        $today = new \DateTimeImmutable();

        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 25);

        // En-tete : emetteur
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 7, $this->encode($seller->getName()), 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, $this->encode($seller->getAddressLine1()), 0, 1);
        $pdf->Cell(0, 5, $this->encode($seller->getPostalCode() . ' ' . $seller->getCity()), 0, 1);
        $pdf->Cell(0, 5, 'SIREN : ' . $seller->getSiren(), 0, 1);

        $pdf->Ln(10);

        // Destinataire (a droite)
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(100);
        $pdf->Cell(0, 5, $this->encode($buyer->getName()), 0, 1, 'L');
        $pdf->Cell(100);
        $pdf->Cell(0, 5, $this->encode($buyer->getAddressLine1()), 0, 1, 'L');
        $pdf->Cell(100);
        $pdf->Cell(0, 5, $this->encode($buyer->getPostalCode() . ' ' . $buyer->getCity()), 0, 1, 'L');

        $pdf->Ln(10);

        // Date et lieu
        $pdf->Cell(0, 5, $this->encode($seller->getCity() . ', le ' . $today->format('d/m/Y')), 0, 1, 'R');

        $pdf->Ln(10);

        // Objet
        $pdf->SetFont('Helvetica', 'B', 11);
        $invoiceNumber = $invoice->getNumber() ?? 'N/A';
        $pdf->Cell(0, 7, $this->encode('Objet : Mise en demeure de payer - Facture ' . $invoiceNumber), 0, 1);

        $pdf->Ln(5);

        // Lettre recommandee
        $pdf->SetFont('Helvetica', 'I', 9);
        $pdf->Cell(0, 5, $this->encode('Lettre recommandee avec accuse de reception'), 0, 1);

        $pdf->Ln(8);

        // Corps
        $pdf->SetFont('Helvetica', '', 10);

        $dueDate = null !== $invoice->getDueDate() ? $invoice->getDueDate()->format('d/m/Y') : 'N/A';
        $amount = $invoice->getTotalIncludingTax() . ' EUR';

        $text = "Madame, Monsieur,\n\n"
            . 'Malgre nos precedentes relances restees sans effet, nous constatons que '
            . 'la facture n° ' . $invoiceNumber . " d'un montant de " . $amount . ', '
            . 'emise le ' . $invoice->getIssueDate()->format('d/m/Y') . ' et echue le ' . $dueDate . ', '
            . "demeure impayee a ce jour.\n\n"
            . 'Par la presente, et conformement aux dispositions des articles L.441-10 '
            . 'et L.441-6 du Code de commerce, nous vous mettons formellement en demeure '
            . 'de proceder au reglement de cette somme dans un delai de HUIT (8) jours '
            . "a compter de la reception de la presente.\n\n"
            . "Nous vous rappelons que, conformement a l'article L.441-10 du Code de commerce, "
            . "des penalites de retard sont exigibles de plein droit, sans qu'un rappel soit necessaire. "
            . "Le taux des penalites de retard est egal a trois fois le taux d'interet legal.\n\n"
            . "Par ailleurs, une indemnite forfaitaire pour frais de recouvrement d'un montant de "
            . "40 euros est due de plein droit (article D.441-5 du Code de commerce).\n\n"
            . "A defaut de reglement dans le delai imparti, nous nous reservons le droit d'engager "
            . 'toute procedure judiciaire pour le recouvrement de cette creance, les frais y afferents '
            . "etant a votre charge.\n\n"
            . "Nous vous prions d'agreer, Madame, Monsieur, l'expression de nos salutations distinguees.";

        $pdf->MultiCell(0, 5, $this->encode($text));

        $pdf->Ln(15);

        // Signature
        $pdf->Cell(0, 5, $this->encode($seller->getName()), 0, 1, 'R');

        return $pdf->Output('S');
    }

    /**
     * Encode les caracteres UTF-8 en ISO-8859-1 pour FPDF.
     */
    private function encode(string $text): string
    {
        $result = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

        return false === $result ? $text : $result;
    }
}
