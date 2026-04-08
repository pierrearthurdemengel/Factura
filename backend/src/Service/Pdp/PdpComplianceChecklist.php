<?php

namespace App\Service\Pdp;

/**
 * Checklist de conformite pour l'immatriculation PDP aupres de la DGFiP.
 *
 * Une Plateforme de Dematerialisation Partenaire (PDP) doit satisfaire
 * un ensemble d'exigences techniques et reglementaires pour etre
 * immatriculee par la DGFiP et pouvoir transmettre des factures
 * electroniques au PPF (Portail Public de Facturation).
 *
 * @phpstan-type ChecklistItem array{id: string, category: string, requirement: string, description: string, status: string, blocking: bool}
 */
class PdpComplianceChecklist
{
    // Statuts des exigences
    public const STATUS_DONE = 'done';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_TODO = 'todo';
    public const STATUS_MANUAL = 'manual_action';

    // Categories
    public const CATEGORY_TECHNICAL = 'technical';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_LEGAL = 'legal';
    public const CATEGORY_OPERATIONAL = 'operational';

    /**
     * Retourne la checklist complete d'immatriculation PDP.
     *
     * @return list<ChecklistItem>
     */
    public function getChecklist(): array
    {
        return [
            // Exigences techniques
            [
                'id' => 'facturx_generation',
                'category' => self::CATEGORY_TECHNICAL,
                'requirement' => 'Generation de factures Factur-X conformes',
                'description' => 'Capacite a generer des factures au format Factur-X (PDF/A-3 + XML CII)',
                'status' => self::STATUS_DONE,
                'blocking' => true,
            ],
            [
                'id' => 'ubl_generation',
                'category' => self::CATEGORY_TECHNICAL,
                'requirement' => 'Generation de factures UBL conformes',
                'description' => 'Capacite a generer des factures au format UBL 2.1 (norme EN 16931)',
                'status' => self::STATUS_DONE,
                'blocking' => true,
            ],
            [
                'id' => 'en16931_validation',
                'category' => self::CATEGORY_TECHNICAL,
                'requirement' => 'Validation EN 16931',
                'description' => 'Validateur de conformite a la norme europeenne de facturation electronique',
                'status' => self::STATUS_DONE,
                'blocking' => true,
            ],
            [
                'id' => 'chorus_pro_integration',
                'category' => self::CATEGORY_TECHNICAL,
                'requirement' => 'Integration Chorus Pro (PISTE)',
                'description' => 'Client API Chorus Pro pour la transmission au secteur public',
                'status' => self::STATUS_DONE,
                'blocking' => true,
            ],
            [
                'id' => 'ppf_direct_transmission',
                'category' => self::CATEGORY_TECHNICAL,
                'requirement' => 'Transmission directe au PPF',
                'description' => 'Capacite a transmettre directement au Portail Public de Facturation',
                'status' => self::STATUS_TODO,
                'blocking' => true,
            ],
            [
                'id' => 'lifecycle_management',
                'category' => self::CATEGORY_TECHNICAL,
                'requirement' => 'Gestion du cycle de vie des factures',
                'description' => 'Suivi des statuts : deposee, recue, acceptee, refusee, payee',
                'status' => self::STATUS_DONE,
                'blocking' => true,
            ],
            [
                'id' => 'archival_10_years',
                'category' => self::CATEGORY_TECHNICAL,
                'requirement' => 'Archivage 10 ans (PAF)',
                'description' => 'Piste d\'Audit Fiable : archivage des factures pendant 10 ans minimum',
                'status' => self::STATUS_DONE,
                'blocking' => true,
            ],

            // Exigences securite
            [
                'id' => 'data_encryption',
                'category' => self::CATEGORY_SECURITY,
                'requirement' => 'Chiffrement des donnees',
                'description' => 'Chiffrement au repos (AES-256) et en transit (TLS 1.2+)',
                'status' => self::STATUS_DONE,
                'blocking' => true,
            ],
            [
                'id' => 'access_control',
                'category' => self::CATEGORY_SECURITY,
                'requirement' => 'Controle d\'acces',
                'description' => 'Authentification forte, gestion des roles, journalisation des acces',
                'status' => self::STATUS_DONE,
                'blocking' => true,
            ],
            [
                'id' => 'security_audit',
                'category' => self::CATEGORY_SECURITY,
                'requirement' => 'Audit de securite',
                'description' => 'Audit de securite par un tiers agree (pentest, revue de code)',
                'status' => self::STATUS_MANUAL,
                'blocking' => true,
            ],
            [
                'id' => 'rgpd_compliance',
                'category' => self::CATEGORY_SECURITY,
                'requirement' => 'Conformite RGPD',
                'description' => 'Registre des traitements, DPO designe, procedures de suppression',
                'status' => self::STATUS_IN_PROGRESS,
                'blocking' => true,
            ],

            // Exigences juridiques
            [
                'id' => 'dgfip_application',
                'category' => self::CATEGORY_LEGAL,
                'requirement' => 'Dossier de candidature DGFiP',
                'description' => 'Depot du dossier d\'immatriculation aupres de la DGFiP',
                'status' => self::STATUS_MANUAL,
                'blocking' => true,
            ],
            [
                'id' => 'qualification_tests',
                'category' => self::CATEGORY_LEGAL,
                'requirement' => 'Tests en environnement de qualification',
                'description' => 'Validation technique sur la plateforme de qualification de la DGFiP',
                'status' => self::STATUS_TODO,
                'blocking' => true,
            ],
            [
                'id' => 'insurance',
                'category' => self::CATEGORY_LEGAL,
                'requirement' => 'Assurance RC Pro',
                'description' => 'Assurance responsabilite civile professionnelle couvrant l\'activite PDP',
                'status' => self::STATUS_MANUAL,
                'blocking' => true,
            ],

            // Exigences operationnelles
            [
                'id' => 'sla_99_9',
                'category' => self::CATEGORY_OPERATIONAL,
                'requirement' => 'Disponibilite 99.9%',
                'description' => 'Garantie de disponibilite du service avec monitoring et alertes',
                'status' => self::STATUS_IN_PROGRESS,
                'blocking' => true,
            ],
            [
                'id' => 'support_process',
                'category' => self::CATEGORY_OPERATIONAL,
                'requirement' => 'Processus de support',
                'description' => 'Support utilisateurs avec SLA de reponse (24h ouvrables)',
                'status' => self::STATUS_TODO,
                'blocking' => false,
            ],
            [
                'id' => 'disaster_recovery',
                'category' => self::CATEGORY_OPERATIONAL,
                'requirement' => 'Plan de reprise d\'activite',
                'description' => 'PRA documente avec RPO < 1h et RTO < 4h',
                'status' => self::STATUS_TODO,
                'blocking' => true,
            ],
        ];
    }

    /**
     * Retourne le pourcentage d'avancement global.
     */
    public function getCompletionRate(): float
    {
        $checklist = $this->getChecklist();
        $total = count($checklist);
        $done = count(array_filter(
            $checklist,
            static fn (array $item): bool => self::STATUS_DONE === $item['status'],
        ));

        return $total > 0 ? round(($done / $total) * 100, 1) : 0.0;
    }

    /**
     * Retourne les elements bloquants non encore completes.
     *
     * @return list<ChecklistItem>
     */
    public function getBlockingItems(): array
    {
        return array_values(array_filter(
            $this->getChecklist(),
            static fn (array $item): bool => $item['blocking'] && self::STATUS_DONE !== $item['status'],
        ));
    }

    /**
     * Retourne les elements necessitant une action manuelle.
     *
     * @return list<ChecklistItem>
     */
    public function getManualActions(): array
    {
        return array_values(array_filter(
            $this->getChecklist(),
            static fn (array $item): bool => self::STATUS_MANUAL === $item['status'],
        ));
    }

    /**
     * Retourne un resume par categorie.
     *
     * @return array<string, array{total: int, done: int, label: string}>
     */
    public function getSummaryByCategory(): array
    {
        $summary = [
            self::CATEGORY_TECHNICAL => ['total' => 0, 'done' => 0, 'label' => 'Technique'],
            self::CATEGORY_SECURITY => ['total' => 0, 'done' => 0, 'label' => 'Securite'],
            self::CATEGORY_LEGAL => ['total' => 0, 'done' => 0, 'label' => 'Juridique'],
            self::CATEGORY_OPERATIONAL => ['total' => 0, 'done' => 0, 'label' => 'Operationnel'],
        ];

        foreach ($this->getChecklist() as $item) {
            if (isset($summary[$item['category']])) {
                ++$summary[$item['category']]['total'];
                if (self::STATUS_DONE === $item['status']) {
                    ++$summary[$item['category']]['done'];
                }
            }
        }

        return $summary;
    }
}
