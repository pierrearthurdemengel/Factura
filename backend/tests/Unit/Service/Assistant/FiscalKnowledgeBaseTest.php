<?php

namespace App\Tests\Unit\Service\Assistant;

use App\Service\Assistant\FiscalKnowledgeBase;
use PHPUnit\Framework\TestCase;

class FiscalKnowledgeBaseTest extends TestCase
{
    private FiscalKnowledgeBase $kb;

    protected function setUp(): void
    {
        $this->kb = new FiscalKnowledgeBase();
    }

    public function testGetRatesContainsAllCategories(): void
    {
        $rates = $this->kb->getRates();

        self::assertArrayHasKey('tva', $rates);
        self::assertArrayHasKey('micro_entrepreneur', $rates);
        self::assertArrayHasKey('cotisations_urssaf', $rates);
        self::assertArrayHasKey('impot_societes', $rates);
        self::assertArrayHasKey('impot_revenu', $rates);
        self::assertArrayHasKey('versement_liberatoire', $rates);
    }

    public function testGetRatesTvaValues(): void
    {
        $rates = $this->kb->getRates();

        self::assertSame('20', $rates['tva']['normal']);
        self::assertSame('10', $rates['tva']['intermediaire']);
        self::assertSame('5.5', $rates['tva']['reduit']);
        self::assertSame('2.1', $rates['tva']['super_reduit']);
    }

    public function testGetRatesMicroPlafonds(): void
    {
        $rates = $this->kb->getRates();

        self::assertSame('188700', $rates['micro_entrepreneur']['plafond_bic_vente']);
        self::assertSame('77700', $rates['micro_entrepreneur']['plafond_bic_service']);
        self::assertSame('77700', $rates['micro_entrepreneur']['plafond_bnc']);
    }

    public function testGetRatesUrssafCotisations(): void
    {
        $rates = $this->kb->getRates();

        self::assertSame('12.3', $rates['cotisations_urssaf']['bic_vente']);
        self::assertSame('21.2', $rates['cotisations_urssaf']['bic_service']);
        self::assertSame('21.1', $rates['cotisations_urssaf']['bnc_liberal']);
    }

    public function testGetRatesImpotSocietes(): void
    {
        $rates = $this->kb->getRates();

        self::assertSame('25', $rates['impot_societes']['taux_normal']);
        self::assertSame('15', $rates['impot_societes']['taux_reduit_pme']);
        self::assertSame('42500', $rates['impot_societes']['seuil_taux_reduit']);
    }

    public function testGetRatesBaremeIr(): void
    {
        $rates = $this->kb->getRates();

        self::assertArrayHasKey('tranches', $rates['impot_revenu']);
        self::assertCount(5, $rates['impot_revenu']['tranches']);
    }

    public function testGetDeductibilityRulesContainsAllCategories(): void
    {
        $rules = $this->kb->getDeductibilityRules();

        self::assertArrayHasKey('frais_deplacement', $rules);
        self::assertArrayHasKey('repas', $rules);
        self::assertArrayHasKey('materiel_informatique', $rules);
        self::assertArrayHasKey('loyer_bureau', $rules);
        self::assertArrayHasKey('assurance_pro', $rules);
        self::assertArrayHasKey('formation', $rules);
        self::assertArrayHasKey('vetements', $rules);
        self::assertArrayHasKey('amendes', $rules);
    }

    public function testDeductibilityMaterielInformatiqueIsDeductible(): void
    {
        $rules = $this->kb->getDeductibilityRules();

        self::assertTrue($rules['materiel_informatique']['deductible']);
        self::assertSame('100', $rules['materiel_informatique']['taux']);
    }

    public function testDeductibilityAmendesNotDeductible(): void
    {
        $rules = $this->kb->getDeductibilityRules();

        self::assertFalse($rules['amendes']['deductible']);
    }

    public function testDeductibilityVetementsNotDeductible(): void
    {
        $rules = $this->kb->getDeductibilityRules();

        self::assertFalse($rules['vetements']['deductible']);
    }

    public function testFindAnswerTva(): void
    {
        $result = $this->kb->findAnswer('quel taux tva en france');

        self::assertNotNull($result);
        self::assertStringContainsString('20%', $result['answer']);
        self::assertSame(FiscalKnowledgeBase::CATEGORY_TVA, $result['category']);
        self::assertNotEmpty($result['references']);
    }

    public function testFindAnswerFranchiseTva(): void
    {
        $result = $this->kb->findAnswer('franchise tva seuil');

        self::assertNotNull($result);
        self::assertStringContainsString('36 800', $result['answer']);
    }

    public function testFindAnswerPlafondMicro(): void
    {
        $result = $this->kb->findAnswer('plafond micro entrepreneur');

        self::assertNotNull($result);
        self::assertStringContainsString('188 700', $result['answer']);
        self::assertSame(FiscalKnowledgeBase::CATEGORY_MICRO, $result['category']);
    }

    public function testFindAnswerCotisationsUrssaf(): void
    {
        $result = $this->kb->findAnswer('taux cotisation urssaf auto-entrepreneur');

        self::assertNotNull($result);
        self::assertStringContainsString('12,3%', $result['answer']);
    }

    public function testFindAnswerReturnsNullForUnknown(): void
    {
        $result = $this->kb->findAnswer('combien fait 2 + 2');

        self::assertNull($result);
    }

    public function testCategorizeTva(): void
    {
        self::assertSame(FiscalKnowledgeBase::CATEGORY_TVA, $this->kb->categorize('tva applicable'));
        self::assertSame(FiscalKnowledgeBase::CATEGORY_TVA, $this->kb->categorize('franchise tva'));
    }

    public function testCategorizeMicro(): void
    {
        self::assertSame(FiscalKnowledgeBase::CATEGORY_MICRO, $this->kb->categorize('plafond micro-entreprise'));
        self::assertSame(FiscalKnowledgeBase::CATEGORY_MICRO, $this->kb->categorize('auto-entrepreneur cotisation'));
        self::assertSame(FiscalKnowledgeBase::CATEGORY_MICRO, $this->kb->categorize('abattement forfaitaire micro'));
    }

    public function testCategorizeUrssaf(): void
    {
        self::assertSame(FiscalKnowledgeBase::CATEGORY_URSSAF, $this->kb->categorize('declaration urssaf'));
    }

    public function testCategorizeDeductibility(): void
    {
        self::assertSame(FiscalKnowledgeBase::CATEGORY_DEDUCTIBILITY, $this->kb->categorize('est-ce deductible'));
    }

    public function testCategorizeRegime(): void
    {
        self::assertSame(FiscalKnowledgeBase::CATEGORY_REGIME, $this->kb->categorize('passage reel'));
        self::assertSame(FiscalKnowledgeBase::CATEGORY_REGIME, $this->kb->categorize('eurl ou sasu'));
        self::assertSame(FiscalKnowledgeBase::CATEGORY_REGIME, $this->kb->categorize('micro vs reel'));
    }

    public function testCategorizeGeneral(): void
    {
        self::assertSame(FiscalKnowledgeBase::CATEGORY_GENERAL, $this->kb->categorize('question generique'));
    }

    public function testNormalizeQuestion(): void
    {
        self::assertSame(
            'quel est le taux de tva en france',
            $this->kb->normalizeQuestion('  Quel est le taux de TVA en France ?  '),
        );
    }

    public function testNormalizeQuestionRemovesPunctuation(): void
    {
        self::assertSame(
            'combien de cotisations urssaf',
            $this->kb->normalizeQuestion('Combien de cotisations URSSAF ?!'),
        );
    }

    public function testNormalizeQuestionMultipleSpaces(): void
    {
        self::assertSame(
            'un deux trois',
            $this->kb->normalizeQuestion('  Un   deux    trois  '),
        );
    }

    public function testHashQuestionDeterministic(): void
    {
        $hash1 = $this->kb->hashQuestion('test question');
        $hash2 = $this->kb->hashQuestion('test question');

        self::assertSame($hash1, $hash2);
        self::assertSame(64, \strlen($hash1)); // SHA-256 = 64 hex chars
    }

    public function testHashQuestionDifferentForDifferentQuestions(): void
    {
        $hash1 = $this->kb->hashQuestion('question un');
        $hash2 = $this->kb->hashQuestion('question deux');

        self::assertNotSame($hash1, $hash2);
    }

    public function testFindAnswerMicroVsReel(): void
    {
        $result = $this->kb->findAnswer('micro vs reel quel regime choisir');

        self::assertNotNull($result);
        self::assertSame(FiscalKnowledgeBase::CATEGORY_REGIME, $result['category']);
        self::assertNotEmpty($result['actions']);
    }

    public function testFindAnswerBaremeIr(): void
    {
        $result = $this->kb->findAnswer('bareme impot revenu tranches');

        self::assertNotNull($result);
        self::assertSame(FiscalKnowledgeBase::CATEGORY_IR, $result['category']);
        self::assertStringContainsString('11%', $result['answer']);
    }

    public function testDeductibilityRulesHaveReferences(): void
    {
        $rules = $this->kb->getDeductibilityRules();

        foreach ($rules as $category => $rule) {
            self::assertNotEmpty($rule['reference'], sprintf('La categorie "%s" doit avoir une reference.', $category));
        }
    }

    public function testDeductibilityRulesHaveExemples(): void
    {
        $rules = $this->kb->getDeductibilityRules();

        foreach ($rules as $category => $rule) {
            self::assertNotEmpty($rule['exemples'], sprintf('La categorie "%s" doit avoir des exemples.', $category));
        }
    }
}
