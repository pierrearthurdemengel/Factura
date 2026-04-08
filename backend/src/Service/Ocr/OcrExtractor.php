<?php

namespace App\Service\Ocr;

/**
 * Extracteur de donnees OCR pour les justificatifs.
 *
 * Pipeline en deux etapes :
 * 1. Extraction du texte brut via Tesseract (appel CLI)
 * 2. Structuration des donnees via l'API (montant, date, fournisseur, TVA)
 *
 * En environnement de dev/test, retourne des donnees simulees.
 * En production, execute le pipeline complet.
 */
class OcrExtractor
{
    public function __construct(
        private readonly string $environment,
    ) {
    }

    /**
     * Extrait les donnees structurees d'un fichier.
     *
     * @return array<string, mixed> Donnees extraites (amount, date, vendor, vatNumber, vatRate, rawText)
     */
    public function extract(string $filePath, string $mimeType): array
    {
        // En dev/test, retourner des donnees simulees
        if ('prod' !== $this->environment) {
            return $this->simulateExtraction($filePath);
        }

        // Etape 1 : extraction du texte brut via Tesseract
        $rawText = $this->extractTextWithTesseract($filePath, $mimeType);

        // Etape 2 : structuration des donnees
        return $this->structureData($rawText);
    }

    /**
     * Extraction du texte via Tesseract OCR (CLI).
     *
     * Pour les PDF, Tesseract ne traite que les images. En production,
     * les PDF sont d'abord convertis en images via Ghostscript.
     */
    private function extractTextWithTesseract(string $filePath, string $mimeType): string
    {
        // Pour les images, appel direct a Tesseract
        if (str_starts_with($mimeType, 'image/')) {
            $output = [];
            $returnCode = 0;
            exec(sprintf('tesseract %s stdout -l fra 2>/dev/null', escapeshellarg($filePath)), $output, $returnCode);

            if (0 !== $returnCode) {
                return '';
            }

            return implode("\n", $output);
        }

        // Pour les PDF, extraction directe du texte via pdftotext
        $output = [];
        $returnCode = 0;
        exec(sprintf('pdftotext %s - 2>/dev/null', escapeshellarg($filePath)), $output, $returnCode);

        if (0 !== $returnCode) {
            return '';
        }

        return implode("\n", $output);
    }

    /**
     * Structure les donnees a partir du texte brut.
     *
     * En production, cette methode appellerait une API de structuration.
     * Pour l'instant, effectue une extraction basique par regex.
     *
     * @return array<string, mixed>
     */
    private function structureData(string $rawText): array
    {
        $data = [
            'rawText' => $rawText,
            'amount' => null,
            'date' => null,
            'vendor' => null,
            'vatNumber' => null,
            'vatRate' => null,
        ];

        // Extraction du montant TTC (pattern francais courant)
        if (preg_match('/(?:total|ttc|montant)\s*:?\s*(\d+[.,]\d{2})\s*(?:EUR|€)?/i', $rawText, $matches)) {
            $data['amount'] = str_replace(',', '.', $matches[1]);
        }

        // Extraction de la date (format DD/MM/YYYY)
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $rawText, $matches)) {
            $data['date'] = $matches[1];
        }

        // Extraction du numero de TVA
        if (preg_match('/FR\s?\d{2}\s?\d{9}/i', $rawText, $matches)) {
            $data['vatNumber'] = preg_replace('/\s/', '', $matches[0]);
        }

        return $data;
    }

    /**
     * Simule une extraction OCR pour l'environnement de dev/test.
     *
     * @return array<string, mixed>
     */
    private function simulateExtraction(string $filePath): array
    {
        return [
            'rawText' => 'Texte simule pour ' . basename($filePath),
            'amount' => '42.50',
            'date' => '08/04/2026',
            'vendor' => 'Fournisseur Simule',
            'vatNumber' => 'FR12345678901',
            'vatRate' => '20',
        ];
    }
}
