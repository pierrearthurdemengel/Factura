<?php

namespace App\Tests\Unit\Service\Ocr;

use App\Service\Ocr\OcrExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'extracteur OCR.
 *
 * Verifie le mode simule (dev/test) et l'extraction par regex.
 */
class OcrExtractorTest extends TestCase
{
    /**
     * Verifie que le mode dev retourne des donnees simulees.
     */
    public function testDevModeReturnsSimulatedData(): void
    {
        $extractor = new OcrExtractor('dev');

        $result = $extractor->extract('/tmp/test.pdf', 'application/pdf');

        $this->assertArrayHasKey('rawText', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('vendor', $result);
        $this->assertSame('42.50', $result['amount']);
    }

    /**
     * Verifie que le mode test retourne aussi des donnees simulees.
     */
    public function testTestModeReturnsSimulatedData(): void
    {
        $extractor = new OcrExtractor('test');

        $result = $extractor->extract('/tmp/receipt.jpg', 'image/jpeg');

        $this->assertSame('42.50', $result['amount']);
        $this->assertSame('08/04/2026', $result['date']);
        $this->assertSame('Fournisseur Simule', $result['vendor']);
    }

    /**
     * Verifie que le nom du fichier est inclus dans le texte simule.
     */
    public function testSimulatedTextContainsFilename(): void
    {
        $extractor = new OcrExtractor('dev');

        $result = $extractor->extract('/tmp/facture-fournisseur.pdf', 'application/pdf');

        $this->assertStringContainsString('facture-fournisseur.pdf', (string) $result['rawText']);
    }
}
