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
    /**
     * Regles de categorisation statiques.
     * Cle : mot-cle (en minuscule), valeur : numero de compte comptable.
     *
     * @var array<string, array{account: string, label: string, confidence: int}>
     */
    private const RULES = [
        // Organismes sociaux
        'urssaf' => ['account' => '646000', 'label' => 'Cotisations sociales exploitant', 'confidence' => 95],
        'cipav' => ['account' => '646000', 'label' => 'Cotisations sociales exploitant', 'confidence' => 95],
        'rsi' => ['account' => '646000', 'label' => 'Cotisations sociales exploitant', 'confidence' => 90],
        'cpam' => ['account' => '645000', 'label' => 'Charges sociales', 'confidence' => 85],

        // Impots et taxes
        'dgfip' => ['account' => '447100', 'label' => 'Impots et taxes', 'confidence' => 90],
        'tresor public' => ['account' => '447100', 'label' => 'Impots et taxes', 'confidence' => 90],
        'impot' => ['account' => '447100', 'label' => 'Impots et taxes', 'confidence' => 80],
        'cfe' => ['account' => '447100', 'label' => 'Cotisation fonciere des entreprises', 'confidence' => 85],

        // Loyers et charges
        'loyer' => ['account' => '613200', 'label' => 'Loyers', 'confidence' => 90],
        'bail' => ['account' => '613200', 'label' => 'Loyers', 'confidence' => 80],
        'charges locatives' => ['account' => '613200', 'label' => 'Charges locatives', 'confidence' => 85],

        // Assurances
        'assurance' => ['account' => '616000', 'label' => 'Assurances', 'confidence' => 85],
        'maif' => ['account' => '616000', 'label' => 'Assurances', 'confidence' => 90],
        'macif' => ['account' => '616000', 'label' => 'Assurances', 'confidence' => 90],
        'axa' => ['account' => '616000', 'label' => 'Assurances', 'confidence' => 85],

        // Telecommunications
        'orange' => ['account' => '626000', 'label' => 'Frais postaux et telecommunications', 'confidence' => 80],
        'sfr' => ['account' => '626000', 'label' => 'Frais postaux et telecommunications', 'confidence' => 80],
        'bouygues' => ['account' => '626000', 'label' => 'Frais postaux et telecommunications', 'confidence' => 80],
        'free mobile' => ['account' => '626000', 'label' => 'Frais postaux et telecommunications', 'confidence' => 85],
        'ovh' => ['account' => '626000', 'label' => 'Frais postaux et telecommunications', 'confidence' => 85],
        'scaleway' => ['account' => '626000', 'label' => 'Frais postaux et telecommunications', 'confidence' => 85],

        // Frais bancaires
        'frais bancaire' => ['account' => '627000', 'label' => 'Services bancaires', 'confidence' => 90],
        'commission carte' => ['account' => '627000', 'label' => 'Services bancaires', 'confidence' => 85],
        'agios' => ['account' => '661000', 'label' => 'Interets des emprunts', 'confidence' => 85],

        // Fournitures de bureau
        'amazon' => ['account' => '606400', 'label' => 'Fournitures de bureau', 'confidence' => 60],
        'fnac' => ['account' => '606400', 'label' => 'Fournitures de bureau', 'confidence' => 60],
        'darty' => ['account' => '606400', 'label' => 'Fournitures de bureau', 'confidence' => 60],

        // Sous-traitance
        'fiverr' => ['account' => '611000', 'label' => 'Sous-traitance', 'confidence' => 75],
        'upwork' => ['account' => '611000', 'label' => 'Sous-traitance', 'confidence' => 75],
        'malt' => ['account' => '611000', 'label' => 'Sous-traitance', 'confidence' => 75],

        // Logiciels et abonnements
        'adobe' => ['account' => '651000', 'label' => 'Redevances et licences', 'confidence' => 85],
        'microsoft' => ['account' => '651000', 'label' => 'Redevances et licences', 'confidence' => 80],
        'google workspace' => ['account' => '651000', 'label' => 'Redevances et licences', 'confidence' => 85],
        'slack' => ['account' => '651000', 'label' => 'Redevances et licences', 'confidence' => 85],
        'notion' => ['account' => '651000', 'label' => 'Redevances et licences', 'confidence' => 85],

        // Transports
        'sncf' => ['account' => '625100', 'label' => 'Deplacements', 'confidence' => 90],
        'ratp' => ['account' => '625100', 'label' => 'Deplacements', 'confidence' => 90],
        'navigo' => ['account' => '625100', 'label' => 'Deplacements', 'confidence' => 90],
        'uber' => ['account' => '625100', 'label' => 'Deplacements', 'confidence' => 80],
        'carburant' => ['account' => '625100', 'label' => 'Deplacements', 'confidence' => 85],
        'total energies' => ['account' => '625100', 'label' => 'Deplacements', 'confidence' => 75],

        // Restauration
        'restaurant' => ['account' => '625700', 'label' => 'Receptions', 'confidence' => 70],
        'deliveroo' => ['account' => '625700', 'label' => 'Receptions', 'confidence' => 65],
        'uber eats' => ['account' => '625700', 'label' => 'Receptions', 'confidence' => 65],

        // Publicite
        'facebook ads' => ['account' => '623000', 'label' => 'Publicite', 'confidence' => 90],
        'google ads' => ['account' => '623000', 'label' => 'Publicite', 'confidence' => 90],
        'linkedin' => ['account' => '623000', 'label' => 'Publicite', 'confidence' => 75],
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
            if (str_contains($normalizedLabel, $keyword)) {
                if ($rule['confidence'] > $bestConfidence) {
                    $bestMatch = $rule;
                    $bestConfidence = $rule['confidence'];
                }
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
