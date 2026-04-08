<?php

namespace App\Service\En16931;

/**
 * Validateur de conformite EN 16931 (norme europeenne de facturation electronique).
 * Verifie que les champs obligatoires de la norme sont presents et valides.
 * Concu pour etre open-source : pas de dependance au reste de l'application.
 */
class En16931Validator
{
    /** @var list<string> Champs obligatoires du Business Group BG-1 (Header) */
    private const REQUIRED_HEADER_FIELDS = [
        'invoiceNumber',
        'issueDate',
        'invoiceTypeCode',
        'currencyCode',
    ];

    /** @var list<string> Champs obligatoires du vendeur (BG-4) */
    private const REQUIRED_SELLER_FIELDS = [
        'sellerName',
        'sellerCountryCode',
    ];

    /** @var list<string> Champs obligatoires de l'acheteur (BG-7) */
    private const REQUIRED_BUYER_FIELDS = [
        'buyerName',
    ];

    /** @var list<string> Codes devises acceptes (ISO 4217 principaux) */
    private const VALID_CURRENCIES = [
        'EUR', 'USD', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'HRK',
    ];

    /** @var list<string> Codes type facture (UNTDID 1001) */
    private const VALID_INVOICE_TYPES = [
        '380', // Facture commerciale
        '381', // Avoir
        '384', // Facture corrigee
        '389', // Autofacture
        '751', // Facture proforma
    ];

    /**
     * Valide un document de facture selon EN 16931.
     *
     * @param array<string, mixed> $document
     *
     * @return array{valid: bool, errors: list<string>}
     */
    public function validate(array $document): array
    {
        $errors = [];

        // Validation des champs obligatoires du header
        foreach (self::REQUIRED_HEADER_FIELDS as $field) {
            if (!isset($document[$field]) || '' === $document[$field]) {
                $errors[] = "Champ obligatoire manquant : {$field} (EN 16931 BT)";
            }
        }

        // Validation du code devise
        $currency = $document['currencyCode'] ?? null;
        if (is_string($currency) && !in_array($currency, self::VALID_CURRENCIES, true)) {
            $errors[] = "Code devise invalide : {$currency} (ISO 4217)";
        }

        // Validation du type de facture
        $typeCode = $document['invoiceTypeCode'] ?? null;
        if (is_string($typeCode) && !in_array($typeCode, self::VALID_INVOICE_TYPES, true)) {
            $errors[] = "Code type facture invalide : {$typeCode} (UNTDID 1001)";
        }

        // Validation du vendeur
        foreach (self::REQUIRED_SELLER_FIELDS as $field) {
            if (!isset($document[$field]) || '' === $document[$field]) {
                $errors[] = "Champ vendeur obligatoire manquant : {$field} (EN 16931 BG-4)";
            }
        }

        // Validation de l'acheteur
        foreach (self::REQUIRED_BUYER_FIELDS as $field) {
            if (!isset($document[$field]) || '' === $document[$field]) {
                $errors[] = "Champ acheteur obligatoire manquant : {$field} (EN 16931 BG-7)";
            }
        }

        // Validation des lignes
        $lines = $document['lines'] ?? null;
        if (!is_array($lines) || [] === $lines) {
            $errors[] = 'Au moins une ligne de facture est requise (EN 16931 BG-25)';
        } else {
            foreach ($lines as $i => $line) {
                if (!is_array($line)) {
                    continue;
                }
                $lineNum = $i + 1;
                if (!isset($line['description']) || '' === $line['description']) {
                    $errors[] = "Ligne {$lineNum} : description obligatoire (BT-153)";
                }
                if (!isset($line['quantity'])) {
                    $errors[] = "Ligne {$lineNum} : quantite obligatoire (BT-129)";
                }
                if (!isset($line['unitPrice'])) {
                    $errors[] = "Ligne {$lineNum} : prix unitaire obligatoire (BT-146)";
                }
            }
        }

        // Validation des totaux
        if (isset($document['totalExcludingTax']) && isset($document['totalIncludingTax'])) {
            $ht = $document['totalExcludingTax'];
            $ttc = $document['totalIncludingTax'];

            if (is_numeric($ht) && is_numeric($ttc)) {
                if (bccomp((string) $ttc, (string) $ht, 2) < 0) {
                    $errors[] = 'Le total TTC ne peut pas etre inferieur au total HT';
                }
            }
        }

        return [
            'valid' => [] === $errors,
            'errors' => $errors,
        ];
    }

    /**
     * Retourne les codes devises valides.
     *
     * @return list<string>
     */
    public function getValidCurrencies(): array
    {
        return self::VALID_CURRENCIES;
    }

    /**
     * Retourne les codes types de facture valides.
     *
     * @return list<string>
     */
    public function getValidInvoiceTypes(): array
    {
        return self::VALID_INVOICE_TYPES;
    }
}
