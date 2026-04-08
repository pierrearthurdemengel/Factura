<?php

namespace App\Service\Country;

use App\Entity\CountryConfig;

/**
 * Factory qui instancie les validateurs et configurations par pays.
 *
 * Chaque pays europeen a ses propres regles de facturation electronique :
 * format (Factur-X, FatturaPA, XRechnung...), protocole de transmission
 * (Chorus Pro, SDI, Peppol...) et taux de TVA.
 *
 * Cette factory centralise la configuration de tous les pays supportes
 * et fournit les CountryConfig correspondants.
 *
 * @phpstan-type CountryData array{code: string, taxAuthority: string, invoiceFormat: string, transmissionProtocol: string, standardVatRate: string, reducedVatRates: list<string>, taxIdFormat: string, eMandatory: bool, mandatoryDate: string|null}
 */
class CountryComplianceFactory
{
    /**
     * Configurations de reference pour les pays europeens prioritaires.
     * Chaque pays sera active uniquement quand un client payant le demande.
     *
     * @var array<string, CountryData>
     */
    private const COUNTRY_DATA = [
        'FR' => [
            'code' => 'FR',
            'taxAuthority' => 'DGFiP',
            'invoiceFormat' => 'Factur-X',
            'transmissionProtocol' => 'Chorus Pro / PPF',
            'standardVatRate' => '20.00',
            'reducedVatRates' => ['10.00', '5.50', '2.10'],
            'taxIdFormat' => '/^FR\d{2}\d{9}$/',
            'eMandatory' => true,
            'mandatoryDate' => '2026-09-01',
        ],
        'IT' => [
            'code' => 'IT',
            'taxAuthority' => 'Agenzia delle Entrate',
            'invoiceFormat' => 'FatturaPA',
            'transmissionProtocol' => 'SDI',
            'standardVatRate' => '22.00',
            'reducedVatRates' => ['10.00', '5.00', '4.00'],
            'taxIdFormat' => '/^IT\d{11}$/',
            'eMandatory' => true,
            'mandatoryDate' => '2019-01-01',
        ],
        'DE' => [
            'code' => 'DE',
            'taxAuthority' => 'Bundeszentralamt fur Steuern',
            'invoiceFormat' => 'XRechnung',
            'transmissionProtocol' => 'Peppol BIS',
            'standardVatRate' => '19.00',
            'reducedVatRates' => ['7.00'],
            'taxIdFormat' => '/^DE\d{9}$/',
            'eMandatory' => true,
            'mandatoryDate' => '2025-01-01',
        ],
        'ES' => [
            'code' => 'ES',
            'taxAuthority' => 'Agencia Tributaria',
            'invoiceFormat' => 'FacturaE',
            'transmissionProtocol' => 'VERI*FACTU / FACe',
            'standardVatRate' => '21.00',
            'reducedVatRates' => ['10.00', '4.00'],
            'taxIdFormat' => '/^ES[A-Z0-9]\d{7}[A-Z0-9]$/',
            'eMandatory' => true,
            'mandatoryDate' => '2026-07-01',
        ],
        'PL' => [
            'code' => 'PL',
            'taxAuthority' => 'Krajowa Administracja Skarbowa',
            'invoiceFormat' => 'KSeF',
            'transmissionProtocol' => 'KSeF API',
            'standardVatRate' => '23.00',
            'reducedVatRates' => ['8.00', '5.00', '0.00'],
            'taxIdFormat' => '/^PL\d{10}$/',
            'eMandatory' => true,
            'mandatoryDate' => '2026-02-01',
        ],
        'BE' => [
            'code' => 'BE',
            'taxAuthority' => 'SPF Finances',
            'invoiceFormat' => 'UBL-BE',
            'transmissionProtocol' => 'Peppol BIS',
            'standardVatRate' => '21.00',
            'reducedVatRates' => ['12.00', '6.00'],
            'taxIdFormat' => '/^BE0\d{9}$/',
            'eMandatory' => true,
            'mandatoryDate' => '2026-01-01',
        ],
        'NL' => [
            'code' => 'NL',
            'taxAuthority' => 'Belastingdienst',
            'invoiceFormat' => 'SI-UBL 2.0',
            'transmissionProtocol' => 'Peppol BIS',
            'standardVatRate' => '21.00',
            'reducedVatRates' => ['9.00'],
            'taxIdFormat' => '/^NL\d{9}B\d{2}$/',
            'eMandatory' => false,
            'mandatoryDate' => null,
        ],
    ];

    /**
     * Retourne la configuration d'un pays par son code ISO 3166-1 alpha-2.
     */
    public function getCountryConfig(string $countryCode): ?CountryConfig
    {
        $code = strtoupper($countryCode);
        $data = self::COUNTRY_DATA[$code] ?? null;

        if (null === $data) {
            return null;
        }

        return $this->buildConfig($data);
    }

    /**
     * Retourne tous les pays supportes.
     *
     * @return list<CountryConfig>
     */
    public function getAllCountries(): array
    {
        $configs = [];

        foreach (self::COUNTRY_DATA as $data) {
            $configs[] = $this->buildConfig($data);
        }

        return $configs;
    }

    /**
     * Retourne les codes des pays supportes.
     *
     * @return list<string>
     */
    public function getSupportedCountryCodes(): array
    {
        return array_keys(self::COUNTRY_DATA);
    }

    /**
     * Retourne les pays ou la facturation electronique est obligatoire.
     *
     * @return list<CountryConfig>
     */
    public function getMandatoryCountries(): array
    {
        $configs = [];

        foreach (self::COUNTRY_DATA as $data) {
            if ($data['eMandatory']) {
                $configs[] = $this->buildConfig($data);
            }
        }

        return $configs;
    }

    /**
     * Verifie si un pays est supporte.
     */
    public function isCountrySupported(string $countryCode): bool
    {
        return isset(self::COUNTRY_DATA[strtoupper($countryCode)]);
    }

    /**
     * Valide un numero d'identification fiscale selon le format du pays.
     */
    public function validateTaxId(string $countryCode, string $taxId): bool
    {
        $data = self::COUNTRY_DATA[strtoupper($countryCode)] ?? null;

        if (null === $data) {
            return false;
        }

        return 1 === preg_match($data['taxIdFormat'], $taxId);
    }

    /**
     * Construit un objet CountryConfig a partir des donnees statiques.
     *
     * @param CountryData $data
     */
    private function buildConfig(array $data): CountryConfig
    {
        $config = new CountryConfig();
        $config->setCountryCode($data['code']);
        $config->setTaxAuthority($data['taxAuthority']);
        $config->setInvoiceFormat($data['invoiceFormat']);
        $config->setTransmissionProtocol($data['transmissionProtocol']);
        $config->setStandardVatRate($data['standardVatRate']);
        $config->setReducedVatRates($data['reducedVatRates']);
        $config->setTaxIdFormat($data['taxIdFormat']);
        $config->setEMandatory($data['eMandatory']);

        if (null !== $data['mandatoryDate']) {
            $config->setMandatoryDate(new \DateTimeImmutable($data['mandatoryDate']));
        }

        return $config;
    }
}
