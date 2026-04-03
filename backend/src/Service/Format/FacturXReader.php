<?php

namespace App\Service\Format;

use horstoeko\zugferd\ZugferdDocumentReader;

/**
 * Lit et parse un fichier Factur-X (XML CII D16B) entrant.
 *
 * Utilise la librairie horstoeko/zugferd pour extraire les donnees
 * structurees d'un fichier XML CII D16B recu via une PDP.
 */
class FacturXReader
{
    /**
     * Parse un fichier XML CII D16B et retourne les donnees structurees.
     *
     * @param string $xmlContent Le contenu XML brut
     *
     * @return array{
     *     number: string,
     *     typeCode: string,
     *     issueDate: \DateTime|null,
     *     currency: string,
     *     seller: array{name: string, address: string, postalCode: string, city: string, country: string, vatNumber: string|null},
     *     buyer: array{name: string, address: string, postalCode: string, city: string, country: string, vatNumber: string|null},
     *     lines: list<array{position: string, description: string, quantity: float, unit: string, unitPrice: float, vatRate: float, lineAmount: float}>,
     *     totalExcludingTax: float,
     *     totalTax: float,
     *     totalIncludingTax: float,
     * }
     */
    public function parse(string $xmlContent): array
    {
        $reader = ZugferdDocumentReader::readAndGuessFromContent($xmlContent);

        // En-tete du document
        $number = null;
        $typeCode = null;
        $issueDate = null;
        $currency = null;
        $reader->getDocumentInformation(
            $number,
            $typeCode,
            $issueDate,
            $deliveryDate,
            $currency,
            $taxCurrency,
            $documentName,
            $documentLanguage,
        );

        // Vendeur
        $sellerName = null;
        $reader->getDocumentSeller($sellerName, $sellerId, $sellerDescription);

        $sellerAddress = null;
        $sellerPostalCode = null;
        $sellerCity = null;
        $sellerCountry = null;
        $reader->getDocumentSellerAddress(
            $sellerAddress,
            $sellerLine2,
            $sellerLine3,
            $sellerPostalCode,
            $sellerCity,
            $sellerCountry,
            $sellerSubDivision,
        );

        $sellerVat = $this->extractVatNumber($reader, 'seller');

        // Acheteur
        $buyerName = null;
        $reader->getDocumentBuyer($buyerName, $buyerId, $buyerDescription);

        $buyerAddress = null;
        $buyerPostalCode = null;
        $buyerCity = null;
        $buyerCountry = null;
        $reader->getDocumentBuyerAddress(
            $buyerAddress,
            $buyerLine2,
            $buyerLine3,
            $buyerPostalCode,
            $buyerCity,
            $buyerCountry,
            $buyerSubDivision,
        );

        $buyerVat = $this->extractVatNumber($reader, 'buyer');

        // Lignes
        $lines = [];
        if ($reader->firstDocumentPosition()) {
            do {
                $lineId = null;
                $reader->getDocumentPositionGenerals($lineId, $lineStatusCode, $lineStatusReasonCode);

                $lineDescription = null;
                $reader->getDocumentPositionProductDetails(
                    $lineDescription,
                    $lineDescFull,
                    $lineSellerAssignedId,
                    $lineBuyerAssignedId,
                    $lineGlobalIdType,
                    $lineGlobalId,
                );

                $lineQuantity = null;
                $lineUnit = null;
                $reader->getDocumentPositionQuantity(
                    $lineQuantity,
                    $lineUnit,
                    $chargeFreeQty,
                    $chargeFreeUnit,
                    $packageQty,
                    $packageUnit,
                );

                $lineUnitPrice = null;
                $reader->getDocumentPositionNetPrice($lineUnitPrice, $basisQty, $basisUnit);

                $lineAmount = null;
                $reader->getDocumentPositionLineSummation($lineAmount, $lineChargeAmount);

                $lineVatRate = 0.0;
                if ($reader->firstDocumentPositionTax()) {
                    $lineTaxCategory = null;
                    $lineTaxType = null;
                    $reader->getDocumentPositionTax(
                        $lineTaxCategory,
                        $lineTaxType,
                        $lineVatRate,
                        $lineCalcAmount,
                        $lineExemptReason,
                        $lineExemptReasonCode,
                    );
                }

                $lines[] = [
                    'position' => $lineId ?? (string) (\count($lines) + 1),
                    'description' => $lineDescription ?? '',
                    'quantity' => $lineQuantity ?? 0.0,
                    'unit' => $lineUnit ?? 'EA',
                    'unitPrice' => $lineUnitPrice ?? 0.0,
                    'vatRate' => $lineVatRate ?? 0.0,
                    'lineAmount' => $lineAmount ?? 0.0,
                ];
            } while ($reader->nextDocumentPosition());
        }

        // Totaux
        $grandTotal = null;
        $duePayable = null;
        $lineTotalAmount = null;
        $taxBasisTotal = null;
        $taxTotal = null;
        $reader->getDocumentSummation(
            $grandTotal,
            $duePayable,
            $lineTotalAmount,
            $chargeTotalAmount,
            $allowanceTotalAmount,
            $taxBasisTotal,
            $taxTotal,
            $roundingAmount,
            $prepaidAmount,
        );

        return [
            'number' => $number ?? '',
            'typeCode' => $typeCode ?? '380',
            'issueDate' => $issueDate,
            'currency' => $currency ?? 'EUR',
            'seller' => [
                'name' => $sellerName ?? '',
                'address' => $sellerAddress ?? '',
                'postalCode' => $sellerPostalCode ?? '',
                'city' => $sellerCity ?? '',
                'country' => $sellerCountry ?? 'FR',
                'vatNumber' => $sellerVat,
            ],
            'buyer' => [
                'name' => $buyerName ?? '',
                'address' => $buyerAddress ?? '',
                'postalCode' => $buyerPostalCode ?? '',
                'city' => $buyerCity ?? '',
                'country' => $buyerCountry ?? 'FR',
                'vatNumber' => $buyerVat,
            ],
            'lines' => $lines,
            'totalExcludingTax' => $taxBasisTotal ?? 0.0,
            'totalTax' => $taxTotal ?? 0.0,
            'totalIncludingTax' => $grandTotal ?? 0.0,
        ];
    }

    /**
     * Extrait le numero de TVA intracommunautaire des enregistrements fiscaux.
     */
    private function extractVatNumber(ZugferdDocumentReader $reader, string $party): ?string
    {
        $taxReg = null;

        if ('seller' === $party) {
            $reader->getDocumentSellerTaxRegistration($taxReg);
        } else {
            $reader->getDocumentBuyerTaxRegistration($taxReg);
        }

        if (null === $taxReg || [] === $taxReg) {
            return null;
        }

        // Le tableau taxReg contient des paires type => id
        foreach ($taxReg as $type => $id) {
            if ('VA' === $type) {
                return \is_string($id) ? $id : null;
            }
        }

        return null;
    }
}
