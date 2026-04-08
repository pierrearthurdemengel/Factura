<?php

namespace App\Tests\Unit\Service\En16931;

use App\Service\En16931\En16931Validator;
use PHPUnit\Framework\TestCase;

class En16931ValidatorTest extends TestCase
{
    private En16931Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new En16931Validator();
    }

    private function validDocument(): array
    {
        return [
            'invoiceNumber' => 'FA-2026-0001',
            'issueDate' => '2026-04-08',
            'invoiceTypeCode' => '380',
            'currencyCode' => 'EUR',
            'sellerName' => 'Mon Entreprise SARL',
            'sellerCountryCode' => 'FR',
            'buyerName' => 'Client SAS',
            'totalExcludingTax' => '1000.00',
            'totalTax' => '200.00',
            'totalIncludingTax' => '1200.00',
            'lines' => [
                [
                    'description' => 'Prestation de developpement',
                    'quantity' => '10',
                    'unitPrice' => '100.00',
                    'vatRate' => '20',
                ],
            ],
        ];
    }

    public function testValidDocumentPasses(): void
    {
        $result = $this->validator->validate($this->validDocument());

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testMissingInvoiceNumberFails(): void
    {
        $doc = $this->validDocument();
        unset($doc['invoiceNumber']);

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('invoiceNumber', $result['errors'][0]);
    }

    public function testMissingIssueDateFails(): void
    {
        $doc = $this->validDocument();
        $doc['issueDate'] = '';

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
    }

    public function testInvalidCurrencyFails(): void
    {
        $doc = $this->validDocument();
        $doc['currencyCode'] = 'XYZ';

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('XYZ', $result['errors'][0]);
    }

    public function testInvalidInvoiceTypeFails(): void
    {
        $doc = $this->validDocument();
        $doc['invoiceTypeCode'] = '999';

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
    }

    public function testMissingSellerNameFails(): void
    {
        $doc = $this->validDocument();
        unset($doc['sellerName']);

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
    }

    public function testMissingBuyerNameFails(): void
    {
        $doc = $this->validDocument();
        $doc['buyerName'] = '';

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
    }

    public function testEmptyLinesFails(): void
    {
        $doc = $this->validDocument();
        $doc['lines'] = [];

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('BG-25', $result['errors'][0]);
    }

    public function testLineMissingDescriptionFails(): void
    {
        $doc = $this->validDocument();
        $doc['lines'][0]['description'] = '';

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('BT-153', $result['errors'][0]);
    }

    public function testLineMissingQuantityFails(): void
    {
        $doc = $this->validDocument();
        unset($doc['lines'][0]['quantity']);

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('BT-129', $result['errors'][0]);
    }

    public function testTotalTtcBelowHtFails(): void
    {
        $doc = $this->validDocument();
        $doc['totalIncludingTax'] = '500.00';

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
    }

    public function testAllValidCurrenciesAccepted(): void
    {
        $currencies = $this->validator->getValidCurrencies();

        foreach ($currencies as $currency) {
            $doc = $this->validDocument();
            $doc['currencyCode'] = $currency;

            $result = $this->validator->validate($doc);
            $this->assertTrue($result['valid'], "Devise {$currency} devrait etre valide");
        }
    }

    public function testAllValidInvoiceTypesAccepted(): void
    {
        $types = $this->validator->getValidInvoiceTypes();

        foreach ($types as $type) {
            $doc = $this->validDocument();
            $doc['invoiceTypeCode'] = $type;

            $result = $this->validator->validate($doc);
            $this->assertTrue($result['valid'], "Type {$type} devrait etre valide");
        }
    }

    public function testMultipleErrorsReported(): void
    {
        $doc = []; // Document completement vide

        $result = $this->validator->validate($doc);

        $this->assertFalse($result['valid']);
        $this->assertGreaterThanOrEqual(5, count($result['errors']));
    }
}
