<?php

namespace App\Service\Accounting;

/**
 * Categorise les transactions bancaires vers des comptes comptables.
 *
 * Utilise un ensemble de regles statiques basees sur les mots-cles
 * du libelle de la transaction. Les regles couvrent les depenses
 * courantes d'une PME / freelance francais.
 */
class TransactionCategorizer
{
    // Comptes comptables
    private const ACCOUNT_SOCIAL_CONTRIBUTIONS = '646000';
    private const ACCOUNT_SOCIAL_CHARGES = '645000';
    private const ACCOUNT_TAX = '447100';
    private const ACCOUNT_RENT = '613200';
    private const ACCOUNT_INSURANCE = '616000';
    private const ACCOUNT_TELECOM = '626000';
    private const ACCOUNT_BANK_SERVICES = '627000';
    private const ACCOUNT_INTEREST = '661000';
    private const ACCOUNT_OFFICE_SUPPLIES = '606400';
    private const ACCOUNT_SUBCONTRACTING = '611000';
    private const ACCOUNT_ROYALTIES = '651000';
    private const ACCOUNT_TRAVEL = '625100';
    private const ACCOUNT_ENTERTAINMENT = '625700';
    private const ACCOUNT_ADVERTISING = '623000';

    // Libelles de categories
    private const LABEL_SOCIAL_CONTRIBUTIONS = 'Cotisations sociales exploitant';
    private const LABEL_TAX = 'Impots et taxes';
    private const LABEL_TELECOM = 'Frais postaux et telecommunications';
    private const LABEL_OFFICE_SUPPLIES = 'Fournitures de bureau';
    private const LABEL_ROYALTIES = 'Redevances et licences';

    /**
     * Regles de categorisation statiques.
     * Cle : mot-cle (en minuscule), valeur : numero de compte comptable.
     *
     * @var array<string, array{account: string, label: string, confidence: int}>
     */
    private const RULES = [
        // Organismes sociaux
        'urssaf' => ['account' => self::ACCOUNT_SOCIAL_CONTRIBUTIONS, 'label' => self::LABEL_SOCIAL_CONTRIBUTIONS, 'confidence' => 95],
        'cipav' => ['account' => self::ACCOUNT_SOCIAL_CONTRIBUTIONS, 'label' => self::LABEL_SOCIAL_CONTRIBUTIONS, 'confidence' => 95],
        'rsi' => ['account' => self::ACCOUNT_SOCIAL_CONTRIBUTIONS, 'label' => self::LABEL_SOCIAL_CONTRIBUTIONS, 'confidence' => 90],
        'cpam' => ['account' => self::ACCOUNT_SOCIAL_CHARGES, 'label' => 'Charges sociales', 'confidence' => 85],

        // Impots et taxes
        'dgfip' => ['account' => self::ACCOUNT_TAX, 'label' => self::LABEL_TAX, 'confidence' => 90],
        'tresor public' => ['account' => self::ACCOUNT_TAX, 'label' => self::LABEL_TAX, 'confidence' => 90],
        'impot' => ['account' => self::ACCOUNT_TAX, 'label' => self::LABEL_TAX, 'confidence' => 80],
        'cfe' => ['account' => self::ACCOUNT_TAX, 'label' => 'Cotisation fonciere des entreprises', 'confidence' => 85],

        // Loyers et charges
        'loyer' => ['account' => self::ACCOUNT_RENT, 'label' => 'Loyers', 'confidence' => 90],
        'bail' => ['account' => self::ACCOUNT_RENT, 'label' => 'Loyers', 'confidence' => 80],
        'charges locatives' => ['account' => self::ACCOUNT_RENT, 'label' => 'Charges locatives', 'confidence' => 85],

        // Assurances
        'assurance' => ['account' => self::ACCOUNT_INSURANCE, 'label' => 'Assurances', 'confidence' => 85],
        'maif' => ['account' => self::ACCOUNT_INSURANCE, 'label' => 'Assurances', 'confidence' => 90],
        'macif' => ['account' => self::ACCOUNT_INSURANCE, 'label' => 'Assurances', 'confidence' => 90],
        'axa' => ['account' => self::ACCOUNT_INSURANCE, 'label' => 'Assurances', 'confidence' => 85],

        // Telecommunications
        'orange' => ['account' => self::ACCOUNT_TELECOM, 'label' => self::LABEL_TELECOM, 'confidence' => 80],
        'sfr' => ['account' => self::ACCOUNT_TELECOM, 'label' => self::LABEL_TELECOM, 'confidence' => 80],
        'bouygues' => ['account' => self::ACCOUNT_TELECOM, 'label' => self::LABEL_TELECOM, 'confidence' => 80],
        'free mobile' => ['account' => self::ACCOUNT_TELECOM, 'label' => self::LABEL_TELECOM, 'confidence' => 85],
        'ovh' => ['account' => self::ACCOUNT_TELECOM, 'label' => self::LABEL_TELECOM, 'confidence' => 85],
        'scaleway' => ['account' => self::ACCOUNT_TELECOM, 'label' => self::LABEL_TELECOM, 'confidence' => 85],

        // Frais bancaires
        'frais bancaire' => ['account' => self::ACCOUNT_BANK_SERVICES, 'label' => 'Services bancaires', 'confidence' => 90],
        'commission carte' => ['account' => self::ACCOUNT_BANK_SERVICES, 'label' => 'Services bancaires', 'confidence' => 85],
        'agios' => ['account' => self::ACCOUNT_INTEREST, 'label' => 'Interets des emprunts', 'confidence' => 85],

        // Fournitures de bureau
        'amazon' => ['account' => self::ACCOUNT_OFFICE_SUPPLIES, 'label' => self::LABEL_OFFICE_SUPPLIES, 'confidence' => 60],
        'fnac' => ['account' => self::ACCOUNT_OFFICE_SUPPLIES, 'label' => self::LABEL_OFFICE_SUPPLIES, 'confidence' => 60],
        'darty' => ['account' => self::ACCOUNT_OFFICE_SUPPLIES, 'label' => self::LABEL_OFFICE_SUPPLIES, 'confidence' => 60],

        // Sous-traitance
        'fiverr' => ['account' => self::ACCOUNT_SUBCONTRACTING, 'label' => 'Sous-traitance', 'confidence' => 75],
        'upwork' => ['account' => self::ACCOUNT_SUBCONTRACTING, 'label' => 'Sous-traitance', 'confidence' => 75],
        'malt' => ['account' => self::ACCOUNT_SUBCONTRACTING, 'label' => 'Sous-traitance', 'confidence' => 75],

        // Logiciels et abonnements
        'adobe' => ['account' => self::ACCOUNT_ROYALTIES, 'label' => self::LABEL_ROYALTIES, 'confidence' => 85],
        'microsoft' => ['account' => self::ACCOUNT_ROYALTIES, 'label' => self::LABEL_ROYALTIES, 'confidence' => 80],
        'google workspace' => ['account' => self::ACCOUNT_ROYALTIES, 'label' => self::LABEL_ROYALTIES, 'confidence' => 85],
        'slack' => ['account' => self::ACCOUNT_ROYALTIES, 'label' => self::LABEL_ROYALTIES, 'confidence' => 85],
        'notion' => ['account' => self::ACCOUNT_ROYALTIES, 'label' => self::LABEL_ROYALTIES, 'confidence' => 85],

        // Transports
        'sncf' => ['account' => self::ACCOUNT_TRAVEL, 'label' => 'Deplacements', 'confidence' => 90],
        'ratp' => ['account' => self::ACCOUNT_TRAVEL, 'label' => 'Deplacements', 'confidence' => 90],
        'navigo' => ['account' => self::ACCOUNT_TRAVEL, 'label' => 'Deplacements', 'confidence' => 90],
        'uber' => ['account' => self::ACCOUNT_TRAVEL, 'label' => 'Deplacements', 'confidence' => 80],
        'carburant' => ['account' => self::ACCOUNT_TRAVEL, 'label' => 'Deplacements', 'confidence' => 85],
        'total energies' => ['account' => self::ACCOUNT_TRAVEL, 'label' => 'Deplacements', 'confidence' => 75],

        // Restauration
        'restaurant' => ['account' => self::ACCOUNT_ENTERTAINMENT, 'label' => 'Receptions', 'confidence' => 70],
        'deliveroo' => ['account' => self::ACCOUNT_ENTERTAINMENT, 'label' => 'Receptions', 'confidence' => 65],
        'uber eats' => ['account' => self::ACCOUNT_ENTERTAINMENT, 'label' => 'Receptions', 'confidence' => 65],

        // Publicite
        'facebook ads' => ['account' => self::ACCOUNT_ADVERTISING, 'label' => 'Publicite', 'confidence' => 90],
        'google ads' => ['account' => self::ACCOUNT_ADVERTISING, 'label' => 'Publicite', 'confidence' => 90],
        'linkedin' => ['account' => self::ACCOUNT_ADVERTISING, 'label' => 'Publicite', 'confidence' => 75],
    ];

    /**
     * Categorise une transaction bancaire a partir de son libelle.
     *
     * @return array{account: string, label: string, confidence: int}|null
     */
    public function categorize(string $transactionLabel): ?array
    {
        $normalizedLabel = mb_strtolower(trim($transactionLabel));

        $bestMatch = null;
        $bestConfidence = 0;

        foreach (self::RULES as $keyword => $rule) {
            if (str_contains($normalizedLabel, $keyword) && $rule['confidence'] > $bestConfidence) {
                $bestMatch = $rule;
                $bestConfidence = $rule['confidence'];
            }
        }

        return $bestMatch;
    }

    /**
     * Retourne toutes les regles de categorisation disponibles.
     *
     * @return array<string, array{account: string, label: string, confidence: int}>
     */
    public static function getRules(): array
    {
        return self::RULES;
    }
}
