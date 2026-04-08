<?php

namespace App\Tests\Unit\Service\Country;

use App\Service\Country\CountryComplianceFactory;
use PHPUnit\Framework\TestCase;

class CountryComplianceFactoryTest extends TestCase
{
    private CountryComplianceFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new CountryComplianceFactory();
    }

    public function testGetCountryConfigForFrance(): void
    {
        $config = $this->factory->getCountryConfig('FR');

        $this->assertNotNull($config);
        $this->assertSame('FR', $config->getCountryCode());
        $this->assertSame('DGFiP', $config->getTaxAuthority());
        $this->assertSame('Factur-X', $config->getInvoiceFormat());
        $this->assertSame('20.00', $config->getStandardVatRate());
        $this->assertTrue($config->isEMandatory());
    }

    public function testGetCountryConfigForItaly(): void
    {
        $config = $this->factory->getCountryConfig('IT');

        $this->assertNotNull($config);
        $this->assertSame('FatturaPA', $config->getInvoiceFormat());
        $this->assertSame('SDI', $config->getTransmissionProtocol());
        $this->assertSame('22.00', $config->getStandardVatRate());
    }

    public function testGetCountryConfigForGermany(): void
    {
        $config = $this->factory->getCountryConfig('DE');

        $this->assertNotNull($config);
        $this->assertSame('XRechnung', $config->getInvoiceFormat());
        $this->assertSame('19.00', $config->getStandardVatRate());
    }

    public function testGetCountryConfigForUnknownCountry(): void
    {
        $this->assertNull($this->factory->getCountryConfig('XX'));
    }

    public function testGetCountryConfigIsCaseInsensitive(): void
    {
        $config = $this->factory->getCountryConfig('fr');

        $this->assertNotNull($config);
        $this->assertSame('FR', $config->getCountryCode());
    }

    public function testGetAllCountriesReturnsAllSupported(): void
    {
        $countries = $this->factory->getAllCountries();

        $this->assertGreaterThanOrEqual(7, count($countries));

        $codes = array_map(
            static fn ($c) => $c->getCountryCode(),
            $countries,
        );

        $this->assertContains('FR', $codes);
        $this->assertContains('IT', $codes);
        $this->assertContains('DE', $codes);
        $this->assertContains('ES', $codes);
        $this->assertContains('PL', $codes);
        $this->assertContains('BE', $codes);
        $this->assertContains('NL', $codes);
    }

    public function testGetSupportedCountryCodes(): void
    {
        $codes = $this->factory->getSupportedCountryCodes();

        $this->assertContains('FR', $codes);
        $this->assertContains('IT', $codes);
        $this->assertContains('DE', $codes);
    }

    public function testGetMandatoryCountries(): void
    {
        $mandatory = $this->factory->getMandatoryCountries();

        foreach ($mandatory as $config) {
            $this->assertTrue($config->isEMandatory());
        }

        $codes = array_map(
            static fn ($c) => $c->getCountryCode(),
            $mandatory,
        );

        // France, Italie, Allemagne, Espagne, Pologne, Belgique sont obligatoires
        $this->assertContains('FR', $codes);
        $this->assertContains('IT', $codes);
        // Les Pays-Bas ne sont pas obligatoires
        $this->assertNotContains('NL', $codes);
    }

    public function testIsCountrySupported(): void
    {
        $this->assertTrue($this->factory->isCountrySupported('FR'));
        $this->assertTrue($this->factory->isCountrySupported('it'));
        $this->assertFalse($this->factory->isCountrySupported('XX'));
    }

    public function testValidateFrenchTaxId(): void
    {
        $this->assertTrue($this->factory->validateTaxId('FR', 'FR12345678901'));
        $this->assertFalse($this->factory->validateTaxId('FR', 'FRABCDEFGH'));
        $this->assertFalse($this->factory->validateTaxId('FR', ''));
    }

    public function testValidateItalianTaxId(): void
    {
        $this->assertTrue($this->factory->validateTaxId('IT', 'IT12345678901'));
        $this->assertFalse($this->factory->validateTaxId('IT', 'IT123'));
    }

    public function testValidateGermanTaxId(): void
    {
        $this->assertTrue($this->factory->validateTaxId('DE', 'DE123456789'));
        $this->assertFalse($this->factory->validateTaxId('DE', 'DE12345'));
    }

    public function testValidateTaxIdForUnknownCountry(): void
    {
        $this->assertFalse($this->factory->validateTaxId('XX', 'XX123'));
    }

    public function testReducedVatRatesForFrance(): void
    {
        $config = $this->factory->getCountryConfig('FR');

        $this->assertNotNull($config);
        $rates = $config->getReducedVatRates();
        $this->assertContains('10.00', $rates);
        $this->assertContains('5.50', $rates);
        $this->assertContains('2.10', $rates);
    }

    public function testMandatoryDateIsSetForFrance(): void
    {
        $config = $this->factory->getCountryConfig('FR');

        $this->assertNotNull($config);
        $date = $config->getMandatoryDate();
        $this->assertNotNull($date);
        $this->assertSame('2026-09-01', $date->format('Y-m-d'));
    }

    public function testNetherlandsIsNotMandatory(): void
    {
        $config = $this->factory->getCountryConfig('NL');

        $this->assertNotNull($config);
        $this->assertFalse($config->isEMandatory());
        $this->assertNull($config->getMandatoryDate());
    }
}
