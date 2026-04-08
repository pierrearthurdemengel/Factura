<?php

namespace App\Service\AdminHub;

/**
 * Repertoire centralise des services administratifs gouvernementaux.
 *
 * Fournit les liens, descriptions et statuts des integrations
 * avec les administrations francaises (URSSAF, DGFiP, INSEE, INPI).
 * Sert de point d'entree unique pour le hub administratif du dashboard.
 *
 * @phpstan-type ServiceInfo array{id: string, name: string, description: string, url: string, status: string, category: string}
 */
class GovernmentApiDirectory
{
    // Categories de services
    public const CATEGORY_FISCAL = 'fiscal';
    public const CATEGORY_SOCIAL = 'social';
    public const CATEGORY_LEGAL = 'legal';
    public const CATEGORY_DATA = 'data';

    // Statuts d'integration
    public const STATUS_INTEGRATED = 'integrated';
    public const STATUS_LINK_ONLY = 'link_only';
    public const STATUS_PLANNED = 'planned';

    /**
     * Retourne la liste de tous les services administratifs disponibles.
     *
     * @return list<ServiceInfo>
     */
    public function getServices(): array
    {
        return [
            [
                'id' => 'urssaf',
                'name' => 'URSSAF',
                'description' => 'Calcul et simulation des cotisations sociales auto-entrepreneur',
                'url' => 'https://www.autoentrepreneur.urssaf.fr',
                'status' => self::STATUS_INTEGRATED,
                'category' => self::CATEGORY_SOCIAL,
            ],
            [
                'id' => 'dgfip_tva',
                'name' => 'DGFiP — TVA',
                'description' => 'Declaration et calcul de TVA (CA3, CA12)',
                'url' => 'https://www.impots.gouv.fr/professionnel',
                'status' => self::STATUS_INTEGRATED,
                'category' => self::CATEGORY_FISCAL,
            ],
            [
                'id' => 'dgfip_fec',
                'name' => 'DGFiP — FEC',
                'description' => 'Export du Fichier des Ecritures Comptables',
                'url' => 'https://www.impots.gouv.fr/professionnel',
                'status' => self::STATUS_INTEGRATED,
                'category' => self::CATEGORY_FISCAL,
            ],
            [
                'id' => 'insee_sirene',
                'name' => 'INSEE Sirene',
                'description' => 'Recherche d\'entreprise par SIREN/SIRET dans la base Sirene',
                'url' => 'https://api.insee.fr/catalogue/site/themes/wso2/subthemes/insee/pages/item-info.jag?name=Sirene&version=V3&provider=insee',
                'status' => self::STATUS_INTEGRATED,
                'category' => self::CATEGORY_DATA,
            ],
            [
                'id' => 'inpi',
                'name' => 'INPI — Guichet Unique',
                'description' => 'Formalites de creation, modification et cessation d\'entreprise',
                'url' => 'https://procedures.inpi.fr',
                'status' => self::STATUS_LINK_ONLY,
                'category' => self::CATEGORY_LEGAL,
            ],
            [
                'id' => 'chorus_pro',
                'name' => 'Chorus Pro',
                'description' => 'Transmission de factures electroniques au secteur public',
                'url' => 'https://chorus-pro.gouv.fr',
                'status' => self::STATUS_INTEGRATED,
                'category' => self::CATEGORY_FISCAL,
            ],
            [
                'id' => 'cfe',
                'name' => 'CFE — Cotisation Fonciere',
                'description' => 'Informations sur la Cotisation Fonciere des Entreprises',
                'url' => 'https://www.impots.gouv.fr/professionnel',
                'status' => self::STATUS_LINK_ONLY,
                'category' => self::CATEGORY_FISCAL,
            ],
        ];
    }

    /**
     * Retourne les services filtres par categorie.
     *
     * @return list<ServiceInfo>
     */
    public function getServicesByCategory(string $category): array
    {
        return array_values(array_filter(
            $this->getServices(),
            static fn (array $service): bool => $service['category'] === $category,
        ));
    }

    /**
     * Retourne uniquement les services avec integration fonctionnelle.
     *
     * @return list<ServiceInfo>
     */
    public function getIntegratedServices(): array
    {
        return array_values(array_filter(
            $this->getServices(),
            static fn (array $service): bool => self::STATUS_INTEGRATED === $service['status'],
        ));
    }

    /**
     * Retourne les categories disponibles avec leur nombre de services.
     *
     * @return array<string, array{label: string, count: int}>
     */
    public function getCategories(): array
    {
        $services = $this->getServices();
        $categories = [
            self::CATEGORY_FISCAL => ['label' => 'Fiscal', 'count' => 0],
            self::CATEGORY_SOCIAL => ['label' => 'Social', 'count' => 0],
            self::CATEGORY_LEGAL => ['label' => 'Juridique', 'count' => 0],
            self::CATEGORY_DATA => ['label' => 'Donnees', 'count' => 0],
        ];

        foreach ($services as $service) {
            if (isset($categories[$service['category']])) {
                ++$categories[$service['category']]['count'];
            }
        }

        return $categories;
    }
}
