<?php

namespace App\Service\AdminHub;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client pour l'API Sirene de l'INSEE.
 *
 * Permet de rechercher des entreprises par SIREN ou SIRET
 * dans la base officielle de l'INSEE. Necessite un token
 * d'API obtenu sur api.insee.fr (inscription gratuite).
 *
 * @phpstan-type SireneResult array{siren: string, denomination: string, siret: string, codeNaf: string, adresse: string, dateCreation: string, etatAdministratif: string}
 */
class InseeClient
{
    private const API_BASE_URL = 'https://api.insee.fr/entreprises/sirene/V3.11';
    private const HEADER_BEARER_PREFIX = 'Bearer ';
    private const CONTENT_TYPE_JSON = 'application/json';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $inseeApiToken = '',
    ) {
    }

    /**
     * Recherche une entreprise par son numero SIREN (9 chiffres).
     *
     * @return SireneResult|null Null si le SIREN n'existe pas ou si l'API est indisponible
     */
    public function findBySiren(string $siren): ?array
    {
        $siren = preg_replace('/\s+/', '', $siren);
        if ('' === $this->inseeApiToken || null === $siren || 1 !== preg_match('/^\d{9}$/', $siren)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL . '/siren/' . $siren, [
                'headers' => [
                    'Authorization' => self::HEADER_BEARER_PREFIX . $this->inseeApiToken,
                    'Accept' => self::CONTENT_TYPE_JSON,
                ],
                'timeout' => 10,
            ]);

            return 200 === $response->getStatusCode() ? $this->mapUniteLegale($response->toArray()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Recherche un etablissement par son numero SIRET (14 chiffres).
     *
     * @return SireneResult|null
     */
    public function findBySiret(string $siret): ?array
    {
        $siret = preg_replace('/\s+/', '', $siret);
        if ('' === $this->inseeApiToken || null === $siret || 1 !== preg_match('/^\d{14}$/', $siret)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL . '/siret/' . $siret, [
                'headers' => [
                    'Authorization' => self::HEADER_BEARER_PREFIX . $this->inseeApiToken,
                    'Accept' => self::CONTENT_TYPE_JSON,
                ],
                'timeout' => 10,
            ]);

            return 200 === $response->getStatusCode() ? $this->mapEtablissement($response->toArray()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Recherche textuelle d'entreprises (denomination, SIREN partiel).
     *
     * @return list<SireneResult>
     */
    public function search(string $query, int $limit = 10): array
    {
        if ('' === $this->inseeApiToken || '' === trim($query)) {
            return [];
        }

        $limit = min($limit, 20);

        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL . '/siren', [
                'headers' => [
                    'Authorization' => self::HEADER_BEARER_PREFIX . $this->inseeApiToken,
                    'Accept' => self::CONTENT_TYPE_JSON,
                ],
                'query' => [
                    'q' => 'denominationUniteLegale:"' . addslashes($query) . '"',
                    'nombre' => $limit,
                ],
                'timeout' => 10,
            ]);

            return 200 === $response->getStatusCode() ? $this->mapUnitesLegalesResponse($response->toArray()) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Mappe la reponse de recherche en liste de resultats.
     *
     * @param array<string, mixed> $data
     *
     * @return list<SireneResult>
     */
    private function mapUnitesLegalesResponse(array $data): array
    {
        $unitesLegales = $data['unitesLegales'] ?? [];
        if (!is_array($unitesLegales)) {
            return [];
        }

        $results = [];
        foreach ($unitesLegales as $unite) {
            if (!is_array($unite)) {
                continue;
            }
            $mapped = $this->mapUniteLegaleItem($unite);
            if (null !== $mapped) {
                $results[] = $mapped;
            }
        }

        return $results;
    }

    /**
     * Verifie si le client est configure (token API present).
     */
    public function isConfigured(): bool
    {
        return '' !== $this->inseeApiToken;
    }

    /**
     * Mappe la reponse API unite legale vers notre format simplifie.
     *
     * @param array<string, mixed> $data
     *
     * @return SireneResult|null
     */
    private function mapUniteLegale(array $data): ?array
    {
        $unite = $data['uniteLegale'] ?? null;
        if (!is_array($unite)) {
            return null;
        }

        return $this->mapUniteLegaleItem($unite);
    }

    /**
     * Mappe un element d'unite legale.
     *
     * @param array<string, mixed> $unite
     *
     * @return SireneResult|null
     */
    private function mapUniteLegaleItem(array $unite): ?array
    {
        $siren = $unite['siren'] ?? '';
        if (!is_string($siren) || '' === $siren) {
            return null;
        }

        // Derniere periode non historisee
        $periodes = $unite['periodesUniteLegale'] ?? [];
        $periode = is_array($periodes) && [] !== $periodes && is_array($periodes[0]) ? $periodes[0] : [];

        $denomination = $periode['denominationUniteLegale'] ?? '';
        if (!is_string($denomination)) {
            $denomination = '';
        }

        // Si pas de denomination, essayer nom + prenom (entreprise individuelle)
        if ('' === $denomination) {
            $nom = $unite['nomUniteLegale'] ?? '';
            $prenom = $unite['prenom1UniteLegale'] ?? '';
            if (is_string($nom) && is_string($prenom)) {
                $denomination = trim($prenom . ' ' . $nom);
            }
        }

        $naf = $periode['activitePrincipaleUniteLegale'] ?? '';
        $etat = $periode['etatAdministratifUniteLegale'] ?? '';
        $dateCreation = $unite['dateCreationUniteLegale'] ?? '';

        return [
            'siren' => $siren,
            'denomination' => $denomination,
            'siret' => $siren . '00000',
            'codeNaf' => is_string($naf) ? $naf : '',
            'adresse' => '',
            'dateCreation' => is_string($dateCreation) ? $dateCreation : '',
            'etatAdministratif' => is_string($etat) ? $etat : '',
        ];
    }

    /**
     * Mappe la reponse API etablissement vers notre format.
     *
     * @param array<string, mixed> $data
     *
     * @return SireneResult|null
     */
    private function mapEtablissement(array $data): ?array
    {
        $etablissement = $data['etablissement'] ?? null;
        if (!is_array($etablissement)) {
            return null;
        }

        $siret = $etablissement['siret'] ?? '';
        $siren = $etablissement['siren'] ?? '';
        if (!is_string($siret) || !is_string($siren)) {
            return null;
        }

        $unite = $etablissement['uniteLegale'] ?? [];
        $denomination = is_array($unite) ? $this->extractDenomination($unite) : '';
        $adresseStr = $this->buildAdresse($etablissement['adresseEtablissement'] ?? []);
        $etat = $this->extractEtatAdministratif($etablissement);
        $naf = is_array($unite) ? $this->extractNaf($unite) : '';
        $dateCreation = $etablissement['dateCreationEtablissement'] ?? '';

        return [
            'siren' => $siren,
            'denomination' => $denomination,
            'siret' => $siret,
            'codeNaf' => $naf,
            'adresse' => $adresseStr,
            'dateCreation' => is_string($dateCreation) ? $dateCreation : '',
            'etatAdministratif' => is_string($etat) ? $etat : '',
        ];
    }

    /**
     * Extrait la denomination d'une unite legale.
     *
     * @param array<string, mixed> $unite
     */
    private function extractDenomination(array $unite): string
    {
        $denomination = $unite['denominationUniteLegale'] ?? '';
        if (is_string($denomination) && '' !== $denomination) {
            return $denomination;
        }

        $nom = $unite['nomUniteLegale'] ?? '';
        $prenom = $unite['prenom1UniteLegale'] ?? '';

        return is_string($nom) && is_string($prenom)
            ? trim($prenom . ' ' . $nom)
            : '';
    }

    /**
     * Construit la chaine d'adresse a partir des donnees d'etablissement.
     */
    private function buildAdresse(mixed $adresse): string
    {
        if (!is_array($adresse)) {
            return '';
        }

        $keys = [
            'numeroVoieEtablissement',
            'typeVoieEtablissement',
            'libelleVoieEtablissement',
            'codePostalEtablissement',
            'libelleCommuneEtablissement',
        ];

        $parts = array_filter(
            array_map(
                static fn (string $key): string => is_string($adresse[$key] ?? null) ? $adresse[$key] : '',
                $keys,
            ),
            static fn (string $part): bool => '' !== $part,
        );

        return implode(' ', $parts);
    }

    /**
     * Extrait l'etat administratif de l'etablissement.
     *
     * @param array<string, mixed> $etablissement
     */
    private function extractEtatAdministratif(array $etablissement): string
    {
        $periodesEtab = $etablissement['periodesEtablissement'] ?? [];
        $periodeEtab = is_array($periodesEtab) && [] !== $periodesEtab && is_array($periodesEtab[0]) ? $periodesEtab[0] : [];

        $etat = $periodeEtab['etatAdministratifEtablissement'] ?? '';

        return is_string($etat) ? $etat : '';
    }

    /**
     * Extrait le code NAF de l'unite legale.
     *
     * @param array<string, mixed> $unite
     */
    private function extractNaf(array $unite): string
    {
        $periodesUnite = $unite['periodesUniteLegale'] ?? [];
        $periodeUnite = is_array($periodesUnite) && [] !== $periodesUnite && is_array($periodesUnite[0]) ? $periodesUnite[0] : [];
        $nafVal = $periodeUnite['activitePrincipaleUniteLegale'] ?? '';

        return is_string($nafVal) ? $nafVal : '';
    }
}
