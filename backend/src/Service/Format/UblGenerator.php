<?php

namespace App\Service\Format;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Exception\CompanyNotFoundException;

/**
 * Genere un fichier XML UBL 2.1 conforme au profil Peppol BIS Billing 3.0.
 *
 * Le XML est produit directement via DOMDocument pour un controle total
 * de la structure et des namespaces. Valide contre le schema XSD OASIS UBL 2.1.
 */
class UblGenerator
{
    private const NAMESPACE_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const NAMESPACE_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NAMESPACE_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';
    private const PROFILE_ID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /**
     * Genere le XML UBL 2.1 a partir d'une entite Invoice.
     *
     * @return string Le contenu XML conforme Peppol BIS 3.0
     */
    public function generate(Invoice $invoice): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NAMESPACE_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NAMESPACE_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NAMESPACE_CBC);
        $dom->appendChild($root);

        // Identifiants de profil
        $this->addCbcElement($dom, $root, 'CustomizationID', self::CUSTOMIZATION_ID);
        $this->addCbcElement($dom, $root, 'ProfileID', self::PROFILE_ID);

        // En-tete
        $this->addCbcElement($dom, $root, 'ID', $invoice->getNumber() ?? '');
        $this->addCbcElement($dom, $root, 'IssueDate', $invoice->getIssueDate()->format('Y-m-d'));

        if (null !== $invoice->getDueDate()) {
            $this->addCbcElement($dom, $root, 'DueDate', $invoice->getDueDate()->format('Y-m-d'));
        }

        $this->addCbcElement($dom, $root, 'InvoiceTypeCode', '380');
        $this->addCbcElement($dom, $root, 'DocumentCurrencyCode', $invoice->getCurrency());

        // Vendeur
        $seller = $invoice->getSeller();
        if (null === $seller) {
            throw new CompanyNotFoundException('La facture doit avoir un vendeur pour generer le UBL.');
        }
        $this->addParty($dom, $root, 'AccountingSupplierParty', $seller);

        // Acheteur
        $buyer = $invoice->getBuyer();
        $this->addBuyerParty($dom, $root, $buyer);

        // Conditions de paiement
        if (null !== $invoice->getPaymentTerms() || null !== $seller->getIban()) {
            $this->addPaymentMeans($dom, $root, $invoice);
        }

        if (null !== $invoice->getPaymentTerms()) {
            $paymentTerms = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PaymentTerms');
            $this->addCbcElement($dom, $paymentTerms, 'Note', $invoice->getPaymentTerms());
            $root->appendChild($paymentTerms);
        }

        // TVA par taux
        $this->addTaxTotal($dom, $root, $invoice);

        // Totaux monetaires
        $this->addLegalMonetaryTotal($dom, $root, $invoice);

        // Lignes
        foreach ($invoice->getLines() as $line) {
            $this->addInvoiceLine($dom, $root, $line);
        }

        $xml = $dom->saveXML();

        return false !== $xml ? $xml : '';
    }

    /**
     * Ajoute un element cbc: au noeud parent.
     */
    private function addCbcElement(
        \DOMDocument $dom,
        \DOMElement $parent,
        string $name,
        string $value,
    ): \DOMElement {
        $element = $dom->createElementNS(self::NAMESPACE_CBC, 'cbc:' . $name, htmlspecialchars($value, ENT_XML1));
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Ajoute un element cbc: avec un attribut currencyID.
     */
    private function addAmountElement(
        \DOMDocument $dom,
        \DOMElement $parent,
        string $name,
        string $amount,
        string $currency,
    ): \DOMElement {
        $element = $dom->createElementNS(self::NAMESPACE_CBC, 'cbc:' . $name, $amount);
        $element->setAttribute('currencyID', $currency);
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Ajoute le bloc vendeur (AccountingSupplierParty).
     */
    private function addParty(
        \DOMDocument $dom,
        \DOMElement $root,
        string $tagName,
        \App\Entity\Company $company,
    ): void {
        $wrapper = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:' . $tagName);
        $party = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:Party');

        // EndpointID (TVA intracommunautaire)
        if (null !== $company->getVatNumber()) {
            $endpoint = $this->addCbcElement($dom, $party, 'EndpointID', $company->getVatNumber());
            $endpoint->setAttribute('schemeID', '0009');
        }

        // Nom
        $partyName = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PartyName');
        $this->addCbcElement($dom, $partyName, 'Name', $company->getName());
        $party->appendChild($partyName);

        // Adresse
        $address = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PostalAddress');
        $this->addCbcElement($dom, $address, 'StreetName', $company->getAddressLine1());
        $this->addCbcElement($dom, $address, 'CityName', $company->getCity());
        $this->addCbcElement($dom, $address, 'PostalZone', $company->getPostalCode());
        $country = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:Country');
        $this->addCbcElement($dom, $country, 'IdentificationCode', $company->getCountryCode());
        $address->appendChild($country);
        $party->appendChild($address);

        // Regime fiscal (TVA)
        if (null !== $company->getVatNumber()) {
            $taxSchemeBlock = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PartyTaxScheme');
            $this->addCbcElement($dom, $taxSchemeBlock, 'CompanyID', $company->getVatNumber());
            $taxScheme = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:TaxScheme');
            $this->addCbcElement($dom, $taxScheme, 'ID', 'VAT');
            $taxSchemeBlock->appendChild($taxScheme);
            $party->appendChild($taxSchemeBlock);
        }

        // Entite legale
        $legalEntity = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PartyLegalEntity');
        $this->addCbcElement($dom, $legalEntity, 'RegistrationName', $company->getName());
        $companyId = $this->addCbcElement($dom, $legalEntity, 'CompanyID', $company->getSiren());
        $companyId->setAttribute('schemeID', '0002');
        $party->appendChild($legalEntity);

        $wrapper->appendChild($party);
        $root->appendChild($wrapper);
    }

    /**
     * Ajoute le bloc acheteur (AccountingCustomerParty).
     */
    private function addBuyerParty(
        \DOMDocument $dom,
        \DOMElement $root,
        \App\Entity\Client $client,
    ): void {
        $wrapper = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:AccountingCustomerParty');
        $party = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:Party');

        // EndpointID
        if (null !== $client->getVatNumber()) {
            $endpoint = $this->addCbcElement($dom, $party, 'EndpointID', $client->getVatNumber());
            $endpoint->setAttribute('schemeID', '0009');
        }

        // Nom
        $partyName = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PartyName');
        $this->addCbcElement($dom, $partyName, 'Name', $client->getName());
        $party->appendChild($partyName);

        // Adresse
        $address = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PostalAddress');
        $this->addCbcElement($dom, $address, 'StreetName', $client->getAddressLine1());
        $this->addCbcElement($dom, $address, 'CityName', $client->getCity());
        $this->addCbcElement($dom, $address, 'PostalZone', $client->getPostalCode());
        $country = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:Country');
        $this->addCbcElement($dom, $country, 'IdentificationCode', $client->getCountryCode());
        $address->appendChild($country);
        $party->appendChild($address);

        // TVA
        if (null !== $client->getVatNumber()) {
            $taxSchemeBlock = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PartyTaxScheme');
            $this->addCbcElement($dom, $taxSchemeBlock, 'CompanyID', $client->getVatNumber());
            $taxScheme = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:TaxScheme');
            $this->addCbcElement($dom, $taxScheme, 'ID', 'VAT');
            $taxSchemeBlock->appendChild($taxScheme);
            $party->appendChild($taxSchemeBlock);
        }

        // Entite legale
        $legalEntity = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PartyLegalEntity');
        $this->addCbcElement($dom, $legalEntity, 'RegistrationName', $client->getName());
        if (null !== $client->getSiren()) {
            $companyId = $this->addCbcElement($dom, $legalEntity, 'CompanyID', $client->getSiren());
            $companyId->setAttribute('schemeID', '0002');
        }
        $party->appendChild($legalEntity);

        $wrapper->appendChild($party);
        $root->appendChild($wrapper);
    }

    /**
     * Ajoute le bloc PaymentMeans (virement bancaire).
     */
    private function addPaymentMeans(\DOMDocument $dom, \DOMElement $root, Invoice $invoice): void
    {
        $paymentMeans = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PaymentMeans');
        // 30 = virement bancaire
        $this->addCbcElement($dom, $paymentMeans, 'PaymentMeansCode', '30');

        $seller = $invoice->getSeller();
        if (null === $seller) {
            return;
        }
        if (null !== $seller->getIban()) {
            $account = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:PayeeFinancialAccount');
            $this->addCbcElement($dom, $account, 'ID', $seller->getIban());
            $this->addCbcElement($dom, $account, 'Name', $seller->getName());

            if (null !== $seller->getBic()) {
                $branch = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:FinancialInstitutionBranch');
                $this->addCbcElement($dom, $branch, 'ID', $seller->getBic());
                $account->appendChild($branch);
            }

            $paymentMeans->appendChild($account);
        }

        $root->appendChild($paymentMeans);
    }

    /**
     * Ajoute le bloc TaxTotal avec ventilation par taux.
     */
    private function addTaxTotal(\DOMDocument $dom, \DOMElement $root, Invoice $invoice): void
    {
        $taxTotal = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:TaxTotal');
        $this->addAmountElement($dom, $taxTotal, 'TaxAmount', $invoice->getTotalTax(), $invoice->getCurrency());

        // Ventilation par taux de TVA
        $groups = $this->groupByVatRate($invoice);
        foreach ($groups as $rate => $amounts) {
            $subtotal = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:TaxSubtotal');
            $this->addAmountElement($dom, $subtotal, 'TaxableAmount', $amounts['basis'], $invoice->getCurrency());
            $this->addAmountElement($dom, $subtotal, 'TaxAmount', $amounts['tax'], $invoice->getCurrency());

            $taxCategory = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:TaxCategory');
            $categoryCode = $this->getTaxCategoryCode((string) $rate, $invoice->getLegalMention());
            $this->addCbcElement($dom, $taxCategory, 'ID', $categoryCode);
            $this->addCbcElement($dom, $taxCategory, 'Percent', number_format((float) $rate, 2, '.', ''));
            $taxScheme = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:TaxScheme');
            $this->addCbcElement($dom, $taxScheme, 'ID', 'VAT');
            $taxCategory->appendChild($taxScheme);
            $subtotal->appendChild($taxCategory);

            $taxTotal->appendChild($subtotal);
        }

        $root->appendChild($taxTotal);
    }

    /**
     * Ajoute le bloc LegalMonetaryTotal.
     */
    private function addLegalMonetaryTotal(\DOMDocument $dom, \DOMElement $root, Invoice $invoice): void
    {
        $monetary = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:LegalMonetaryTotal');
        $currency = $invoice->getCurrency();

        $this->addAmountElement($dom, $monetary, 'LineExtensionAmount', $invoice->getTotalExcludingTax(), $currency);
        $this->addAmountElement($dom, $monetary, 'TaxExclusiveAmount', $invoice->getTotalExcludingTax(), $currency);
        $this->addAmountElement($dom, $monetary, 'TaxInclusiveAmount', $invoice->getTotalIncludingTax(), $currency);
        $this->addAmountElement($dom, $monetary, 'PayableAmount', $invoice->getTotalIncludingTax(), $currency);

        $root->appendChild($monetary);
    }

    /**
     * Ajoute une ligne de facture UBL (InvoiceLine).
     */
    private function addInvoiceLine(\DOMDocument $dom, \DOMElement $root, InvoiceLine $line): void
    {
        $lineEl = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:InvoiceLine');

        $this->addCbcElement($dom, $lineEl, 'ID', (string) $line->getPosition());

        $qty = $dom->createElementNS(
            self::NAMESPACE_CBC,
            'cbc:InvoicedQuantity',
            (string) $line->getQuantity(),
        );
        $qty->setAttribute('unitCode', $line->getUnit());
        $lineEl->appendChild($qty);

        $this->addAmountElement(
            $dom,
            $lineEl,
            'LineExtensionAmount',
            $line->getLineAmount(),
            $line->getInvoice()->getCurrency(),
        );

        // Item
        $item = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:Item');
        $this->addCbcElement($dom, $item, 'Name', $line->getDescription());

        $taxCategory = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:ClassifiedTaxCategory');
        $categoryCode = $this->getTaxCategoryCode($line->getVatRate(), null);
        $this->addCbcElement($dom, $taxCategory, 'ID', $categoryCode);
        $rate = is_numeric($line->getVatRate()) ? $line->getVatRate() : '0.00';
        $this->addCbcElement($dom, $taxCategory, 'Percent', number_format((float) $rate, 2, '.', ''));
        $taxScheme = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:TaxScheme');
        $this->addCbcElement($dom, $taxScheme, 'ID', 'VAT');
        $taxCategory->appendChild($taxScheme);
        $item->appendChild($taxCategory);
        $lineEl->appendChild($item);

        // Prix unitaire
        $price = $dom->createElementNS(self::NAMESPACE_CAC, 'cac:Price');
        $this->addAmountElement(
            $dom,
            $price,
            'PriceAmount',
            $line->getUnitPriceExcludingTax(),
            $line->getInvoice()->getCurrency(),
        );
        $lineEl->appendChild($price);

        $root->appendChild($lineEl);
    }

    /**
     * Determine le code categorie TVA.
     */
    private function getTaxCategoryCode(string $vatRate, ?string $legalMention): string
    {
        return match (true) {
            'AE' === $vatRate => 'AE',
            'E' === $vatRate || (null !== $legalMention && str_contains($legalMention, '293 B')) => 'E',
            in_array($vatRate, ['Z', '0', '0.00'], true) => 'Z',
            default => 'S',
        };
    }

    /**
     * Regroupe les lignes par taux de TVA.
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
