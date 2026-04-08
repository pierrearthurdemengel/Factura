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
        if ('' === $this->inseeApiToken) {
            return null;
        }

        $siren = preg_replace('/\s+/', '', $siren);
        if (null === $siren || 1 !== preg_match('/^\d{9}$/', $siren)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL . '/siren/' . $siren, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->inseeApiToken,
                    'Accept' => 'application/json',
                ],
                'timeout' => 10,
            ]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $data = $response->toArray();

            return $this->mapUniteLegale($data);
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
        if ('' === $this->inseeApiToken) {
            return null;
        }

        $siret = preg_replace('/\s+/', '', $siret);
        if (null === $siret || 1 !== preg_match('/^\d{14}$/', $siret)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL . '/siret/' . $siret, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->inseeApiToken,
                    'Accept' => 'application/json',
                ],
                'timeout' => 10,
            ]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $data = $response->toArray();

            return $this->mapEtablissement($data);
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
                    'Authorization' => 'Bearer ' . $this->inseeApiToken,
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'q' => 'denominationUniteLegale:"' . addslashes($query) . '"',
                    'nombre' => $limit,
                ],
                'timeout' => 10,
            ]);

            if (200 !== $response->getStatusCode()) {
                return [];
            }

            $data = $response->toArray();
            $results = [];

            $unitesLegales = $data['unitesLegales'] ?? [];
            if (!is_array($unitesLegales)) {
                return [];
            }

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
        } catch (\Throwable) {
            return [];
        }
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
        $adresse = $etablissement['adresseEtablissement'] ?? [];

        $denomination = '';
        if (is_array($unite)) {
            $denomination = $unite['denominationUniteLegale'] ?? '';
            if (!is_string($denomination) || '' === $denomination) {
                $nom = $unite['nomUniteLegale'] ?? '';
                $prenom = $unite['prenom1UniteLegale'] ?? '';
                $denomination = is_string($nom) && is_string($prenom)
                    ? trim($prenom . ' ' . $nom)
                    : '';
            }
        }

        $adresseStr = '';
        if (is_array($adresse)) {
            $parts = array_filter([
                is_string($adresse['numeroVoieEtablissement'] ?? null) ? $adresse['numeroVoieEtablissement'] : '',
                is_string($adresse['typeVoieEtablissement'] ?? null) ? $adresse['typeVoieEtablissement'] : '',
                is_string($adresse['libelleVoieEtablissement'] ?? null) ? $adresse['libelleVoieEtablissement'] : '',
                is_string($adresse['codePostalEtablissement'] ?? null) ? $adresse['codePostalEtablissement'] : '',
                is_string($adresse['libelleCommuneEtablissement'] ?? null) ? $adresse['libelleCommuneEtablissement'] : '',
            ], static fn (string $part): bool => '' !== $part);
            $adresseStr = implode(' ', $parts);
        }

        $periodesEtab = $etablissement['periodesEtablissement'] ?? [];
        $periodeEtab = is_array($periodesEtab) && [] !== $periodesEtab && is_array($periodesEtab[0]) ? $periodesEtab[0] : [];
        $etat = $periodeEtab['etatAdministratifEtablissement'] ?? '';

        $naf = '';
        if (is_array($unite)) {
            $periodesUnite = $unite['periodesUniteLegale'] ?? [];
            $periodeUnite = is_array($periodesUnite) && [] !== $periodesUnite && is_array($periodesUnite[0]) ? $periodesUnite[0] : [];
            $nafVal = $periodeUnite['activitePrincipaleUniteLegale'] ?? '';
            $naf = is_string($nafVal) ? $nafVal : '';
        }

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
}
