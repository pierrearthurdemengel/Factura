<?php

namespace App\Service\Format;

use App\Entity\Invoice;

/**
 * Genere un fichier XML CII D16B standalone (sans PDF).
 *
 * Delegue la generation au FacturXGenerator qui produit deja
 * du CII D16B conforme EN 16931. Le CII standalone est le meme
 * XML, sans l'enveloppe PDF/A-3.
 */
class CiiGenerator
{
    public function __construct(
        private readonly FacturXGenerator $facturXGenerator,
    ) {
    }

    /**
     * Genere le XML CII D16B a partir d'une entite Invoice.
     *
     * @return string Le contenu XML CII D16B conforme EN 16931
     */
    public function generate(Invoice $invoice): string
    {
        // Le FacturXGenerator produit du XML CII D16B natif.
        // En mode standalone, on retourne directement le XML sans PDF.
        return $this->facturXGenerator->generate($invoice);
    }
}
