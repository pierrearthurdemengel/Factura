<?php

namespace App\Service\Assistant;

/**
 * Base de connaissances fiscales francaises.
 *
 * Centralise les regles fiscales (CGI, BOI, URSSAF), les seuils
 * et taux en vigueur, et les regles de deductibilite par categorie.
 * Mise a jour annuelle des baremes lors de chaque Loi de Finances.
 */
class FiscalKnowledgeBase
{
    // Categories de questions reconnues
    public const CATEGORY_TVA = 'tva';
    public const CATEGORY_MICRO = 'micro_entrepreneur';
    public const CATEGORY_IS = 'impot_societes';
    public const CATEGORY_IR = 'impot_revenu';
    public const CATEGORY_URSSAF = 'urssaf';
    public const CATEGORY_DEDUCTIBILITY = 'deductibilite';
    public const CATEGORY_REGIME = 'regime_fiscal';
    public const CATEGORY_CREATION = 'creation_entreprise';
    public const CATEGORY_GENERAL = 'general';

    /**
     * Baremes et seuils en vigueur pour 2026.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRates(): array
    {
        return [
            'tva' => [
                'normal' => '20',
                'intermediaire' => '10',
                'reduit' => '5.5',
                'super_reduit' => '2.1',
                'reference' => 'Article 278 et suivants du CGI',
            ],
            'micro_entrepreneur' => [
                'plafond_bic_vente' => '188700',
                'plafond_bic_service' => '77700',
                'plafond_bnc' => '77700',
                'abattement_bic_vente' => '71',
                'abattement_bic_service' => '50',
                'abattement_bnc' => '34',
                'reference' => 'Article 50-0 et 102 ter du CGI',
            ],
            'cotisations_urssaf' => [
                'bic_vente' => '12.3',
                'bic_service' => '21.2',
                'bnc_liberal' => '21.1',
                'bnc_cipav' => '21.2',
                'reference' => 'Decret URSSAF auto-entrepreneur 2026',
            ],
            'impot_societes' => [
                'taux_normal' => '25',
                'taux_reduit_pme' => '15',
                'seuil_taux_reduit' => '42500',
                'seuil_ca_pme' => '10000000',
                'reference' => 'Article 219 du CGI',
            ],
            'impot_revenu' => [
                'tranches' => [
                    ['min' => '0', 'max' => '11294', 'taux' => '0'],
                    ['min' => '11295', 'max' => '28797', 'taux' => '11'],
                    ['min' => '28798', 'max' => '82341', 'taux' => '30'],
                    ['min' => '82342', 'max' => '177106', 'taux' => '41'],
                    ['min' => '177107', 'max' => null, 'taux' => '45'],
                ],
                'reference' => 'Article 197 du CGI - Bareme 2026',
            ],
            'versement_liberatoire' => [
                'bic_vente' => '1',
                'bic_service' => '1.7',
                'bnc' => '2.2',
                'plafond_rfr' => '27478',
                'reference' => 'Article 151-0 du CGI',
            ],
        ];
    }

    /**
     * Regles de deductibilite par categorie de depense.
     *
     * @return array<string, array{deductible: bool, taux: string|null, conditions: string, reference: string, exemples: list<string>}>
     */
    public function getDeductibilityRules(): array
    {
        return [
            'frais_deplacement' => [
                'deductible' => true,
                'taux' => null,
                'conditions' => 'Deplacement professionnel justifie. Bareme kilometrique ou frais reels.',
                'reference' => 'Article 83-2° du CGI, BOI-BNC-BASE-40-60-40',
                'exemples' => ['Frais kilometriques', 'Peages', 'Billets de train', 'Location vehicule'],
            ],
            'repas' => [
                'deductible' => true,
                'taux' => null,
                'conditions' => 'Part excedant le forfait repas a domicile (5.35 EUR) et inferieure au plafond (20.70 EUR).',
                'reference' => 'BOI-BNC-BASE-40-60-60',
                'exemples' => ['Restaurant client', 'Dejeuner en deplacement'],
            ],
            'materiel_informatique' => [
                'deductible' => true,
                'taux' => '100',
                'conditions' => 'Usage professionnel exclusif ou au prorata de l\'usage professionnel.',
                'reference' => 'BOI-BNC-BASE-40-30',
                'exemples' => ['Ordinateur portable', 'Ecran', 'Imprimante', 'Logiciels'],
            ],
            'loyer_bureau' => [
                'deductible' => true,
                'taux' => '100',
                'conditions' => 'Local a usage professionnel. Si mixte (domicile), au prorata de la surface dediee.',
                'reference' => 'BOI-BNC-BASE-40-20',
                'exemples' => ['Loyer local professionnel', 'Coworking', 'Part professionnelle du loyer domicile'],
            ],
            'telephone_internet' => [
                'deductible' => true,
                'taux' => null,
                'conditions' => 'Au prorata de l\'usage professionnel si usage mixte.',
                'reference' => 'BOI-BNC-BASE-40-60-10',
                'exemples' => ['Forfait mobile pro', 'Abonnement internet', 'Ligne fixe bureau'],
            ],
            'assurance_pro' => [
                'deductible' => true,
                'taux' => '100',
                'conditions' => 'Assurance liee a l\'activite professionnelle (RC Pro, multirisque).',
                'reference' => 'BOI-BNC-BASE-40-50',
                'exemples' => ['RC Professionnelle', 'Multirisque bureau', 'Prevoyance Madelin'],
            ],
            'formation' => [
                'deductible' => true,
                'taux' => '100',
                'conditions' => 'Formation en lien direct avec l\'activite professionnelle.',
                'reference' => 'Article 93-1 du CGI',
                'exemples' => ['Formation technique', 'Conference professionnelle', 'Livres techniques'],
            ],
            'vetements' => [
                'deductible' => false,
                'taux' => null,
                'conditions' => 'Non deductible sauf vetements specifiques a la profession (blouse, uniforme).',
                'reference' => 'BOI-BNC-BASE-40-60-20',
                'exemples' => ['Costume', 'Chaussures de ville'],
            ],
            'amendes' => [
                'deductible' => false,
                'taux' => null,
                'conditions' => 'Les amendes et penalites ne sont jamais deductibles.',
                'reference' => 'Article 39-2 du CGI',
                'exemples' => ['Amende de stationnement', 'Penalites fiscales', 'Majorations'],
            ],
            'cadeaux_clients' => [
                'deductible' => true,
                'taux' => null,
                'conditions' => 'Deductible si montant raisonnable et en rapport avec l\'activite. TVA recuperable si < 73 EUR TTC par an et par beneficiaire.',
                'reference' => 'BOI-BIC-CHG-40-20-40',
                'exemples' => ['Cadeau fin d\'annee', 'Invitation restaurant client'],
            ],
        ];
    }

    /**
     * Repond a une question a partir de la base de connaissances.
     *
     * @return array{answer: string, references: list<string>, category: string, actions: list<string>}|null
     */
    public function findAnswer(string $normalizedQuestion): ?array
    {
        $rules = $this->getKnowledgeRules();

        foreach ($rules as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (str_contains($normalizedQuestion, $keyword)) {
                    return [
                        'answer' => $rule['answer'],
                        'references' => $rule['references'],
                        'category' => $rule['category'],
                        'actions' => $rule['actions'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Categorise une question en langage naturel.
     */
    public function categorize(string $normalizedQuestion): string
    {
        $categoryKeywords = [
            // Les categories les plus specifiques en premier pour eviter les faux positifs
            self::CATEGORY_REGIME => ['regime reel', 'regime micro', 'passage reel', 'passage societe', 'eurl', 'sasu', 'micro vs reel', 'ei vs societe'],
            self::CATEGORY_TVA => ['tva', 'taxe valeur ajoutee', 'taux tva', 'franchise tva', 'ca3', 'ca12'],
            self::CATEGORY_MICRO => ['micro-entrepreneur', 'auto-entrepreneur', 'autoentrepreneur', 'micro-entreprise', 'abattement forfaitaire'],
            self::CATEGORY_IS => ['impot societe', 'is ', 'taux is', 'benefice societe'],
            self::CATEGORY_IR => ['impot revenu', 'bareme progressif', 'tranche', 'quotient familial'],
            self::CATEGORY_URSSAF => ['urssaf', 'cotisation', 'charge sociale', 'declaration urssaf'],
            self::CATEGORY_DEDUCTIBILITY => ['deductible', 'deduire', 'deduction', 'charge deductible', 'frais professionnels'],
            self::CATEGORY_CREATION => ['creer entreprise', 'creation', 'statut juridique', 'forme juridique'],
        ];

        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalizedQuestion, $keyword)) {
                    return $category;
                }
            }
        }

        return self::CATEGORY_GENERAL;
    }

    /**
     * Normalise une question pour la comparaison et le cache.
     */
    public function normalizeQuestion(string $question): string
    {
        $normalized = mb_strtolower(trim($question));

        // Supprimer la ponctuation finale (avec espaces eventuels avant)
        $normalized = (string) preg_replace('/\s*[?!.,;:]+\s*$/', '', $normalized);

        // Normaliser les espaces multiples
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Calcule le hash d'une question normalisee pour le cache.
     */
    public function hashQuestion(string $normalizedQuestion): string
    {
        return hash('sha256', $normalizedQuestion);
    }

    /**
     * Regles de la base de connaissances avec mots-cles de correspondance.
     *
     * @return list<array{keywords: list<string>, answer: string, references: list<string>, category: string, actions: list<string>}>
     */
    private function getKnowledgeRules(): array
    {
        return [
            [
                'keywords' => ['taux tva', 'quel taux tva', 'taux de tva'],
                'answer' => 'En France, il existe 4 taux de TVA : le taux normal de 20% (applicable a la majorite des biens et services), le taux intermediaire de 10% (restauration, transports, travaux de renovation), le taux reduit de 5,5% (produits alimentaires, livres, energie) et le taux super-reduit de 2,1% (medicaments rembourses, presse).',
                'references' => ['Article 278 du CGI (taux normal)', 'Article 278 bis du CGI (taux intermediaire)', 'Article 278-0 bis du CGI (taux reduit)'],
                'category' => self::CATEGORY_TVA,
                'actions' => [],
            ],
            [
                'keywords' => ['franchise tva', 'seuil tva', 'franchise en base'],
                'answer' => 'La franchise en base de TVA dispense de facturer la TVA. Seuils 2026 : 36 800 EUR pour les prestations de services (tolerance : 39 100 EUR) et 91 900 EUR pour les activites de vente (tolerance : 101 000 EUR). En cas de depassement du seuil majore, la TVA est applicable des le 1er jour du mois de depassement.',
                'references' => ['Article 293 B du CGI', 'BOI-TVA-DECLA-40-10-10'],
                'category' => self::CATEGORY_TVA,
                'actions' => ['Verifier votre CA sur la periode en cours'],
            ],
            [
                'keywords' => ['plafond micro', 'seuil micro', 'plafond auto-entrepreneur', 'plafond autoentrepreneur'],
                'answer' => 'Plafonds du regime micro-entrepreneur 2026 : 188 700 EUR pour les activites de vente de marchandises (BIC vente), 77 700 EUR pour les prestations de services (BIC service et BNC). En cas de depassement sur 2 annees consecutives, basculement automatique au regime reel.',
                'references' => ['Article 50-0 du CGI', 'Article 102 ter du CGI'],
                'category' => self::CATEGORY_MICRO,
                'actions' => ['Simuler le passage au reel'],
            ],
            [
                'keywords' => ['cotisation urssaf', 'taux cotisation', 'charge urssaf', 'cotisation auto-entrepreneur'],
                'answer' => 'Taux de cotisations URSSAF auto-entrepreneur 2026 : vente de marchandises (BIC) : 12,3%, prestations de services BIC : 21,2%, prestations de services BNC (liberal) : 21,1%, professions liberales CIPAV : 21,2%. Ces taux incluent toutes les cotisations sociales obligatoires.',
                'references' => ['Decret n°2024-xxx (taux URSSAF 2026)', 'Article L613-7 du Code de la securite sociale'],
                'category' => self::CATEGORY_URSSAF,
                'actions' => ['Calculer vos cotisations'],
            ],
            [
                'keywords' => ['versement liberatoire', 'prelevement liberatoire'],
                'answer' => 'Le versement liberatoire de l\'impot sur le revenu est une option pour les micro-entrepreneurs. Taux : 1% (vente), 1,7% (BIC services), 2,2% (BNC). Condition : revenu fiscal de reference N-2 inferieur a 27 478 EUR par part de quotient familial. L\'option se prend aupres de l\'URSSAF avant le 30 septembre.',
                'references' => ['Article 151-0 du CGI'],
                'category' => self::CATEGORY_MICRO,
                'actions' => ['Verifier votre eligibilite au versement liberatoire'],
            ],
            [
                'keywords' => ['impot societe', 'taux is', 'is pme'],
                'answer' => 'L\'impot sur les societes en 2026 : taux normal de 25%. Taux reduit PME de 15% sur les 42 500 premiers euros de benefice, reserve aux societes dont le CA est inferieur a 10 MEUR et dont le capital est entierement libere et detenu a 75% par des personnes physiques.',
                'references' => ['Article 219-I du CGI'],
                'category' => self::CATEGORY_IS,
                'actions' => ['Estimer votre IS'],
            ],
            [
                'keywords' => ['bareme impot revenu', 'tranche impot', 'calcul impot revenu', 'bareme ir'],
                'answer' => 'Bareme progressif de l\'impot sur le revenu 2026 (revenus 2025) : 0% jusqu\'a 11 294 EUR, 11% de 11 295 a 28 797 EUR, 30% de 28 798 a 82 341 EUR, 41% de 82 342 a 177 106 EUR, 45% au-dela. Application du quotient familial, plafonnement de l\'avantage a 1 759 EUR par demi-part.',
                'references' => ['Article 197 du CGI'],
                'category' => self::CATEGORY_IR,
                'actions' => ['Estimer votre impot sur le revenu'],
            ],
            [
                'keywords' => ['frais kilometrique', 'bareme kilometrique', 'indemnite kilometrique'],
                'answer' => 'Le bareme kilometrique permet de deduire les frais de deplacement professionnel. Pour un vehicule de 5 CV fiscaux : 0,603 EUR/km jusqu\'a 5 000 km, (0,339 EUR x d) + 1 320 EUR de 5 001 a 20 000 km, 0,405 EUR/km au-dela. Justificatifs requis : agenda des deplacements, objet professionnel.',
                'references' => ['BOI-BAREME-000001', 'BOI-BNC-BASE-40-60-40'],
                'category' => self::CATEGORY_DEDUCTIBILITY,
                'actions' => [],
            ],
            [
                'keywords' => ['deduire repas', 'frais repas', 'repas deductible'],
                'answer' => 'Les frais de repas pris individuellement sur le lieu de travail sont deductibles pour la part excedant le forfait repas a domicile (5,35 EUR en 2026) et inferieure au plafond (20,70 EUR). Soit une deduction maximale de 15,35 EUR par repas. Justificatif obligatoire.',
                'references' => ['BOI-BNC-BASE-40-60-60'],
                'category' => self::CATEGORY_DEDUCTIBILITY,
                'actions' => [],
            ],
            [
                'keywords' => ['micro vs reel', 'passer au reel', 'comparaison micro reel', 'regime reel ou micro'],
                'answer' => 'Le choix entre micro et reel depend du niveau de charges reelles. En micro, l\'abattement forfaitaire est de 71% (BIC vente), 50% (BIC service) ou 34% (BNC). Si vos charges reelles depassent cet abattement, le regime reel est plus avantageux. Utilisez notre simulateur pour comparer.',
                'references' => ['Article 50-0 du CGI', 'Article 102 ter du CGI'],
                'category' => self::CATEGORY_REGIME,
                'actions' => ['Lancer la simulation micro vs reel'],
            ],
            [
                'keywords' => ['eurl ou sasu', 'ei vs societe', 'passer en societe', 'creer societe'],
                'answer' => 'Le passage en societe (EURL ou SASU) est generalement pertinent quand le benefice depasse 40 000 EUR. L\'EURL (IS option) permet d\'optimiser la remuneration vs dividendes. La SASU permet le statut assimile-salarie (pas de RSI). Attention aux couts de gestion supplementaires.',
                'references' => ['Article 1832 du Code civil', 'Article 8 du CGI (EURL IR)', 'Article 206 du CGI (IS)'],
                'category' => self::CATEGORY_REGIME,
                'actions' => ['Lancer la simulation EI vs societe'],
            ],
        ];
    }
}
