<?php

namespace App\Tests\Unit\Service\Pdp;

use App\Service\Pdp\PdpComplianceChecklist;
use PHPUnit\Framework\TestCase;

class PdpComplianceChecklistTest extends TestCase
{
    private PdpComplianceChecklist $checklist;

    protected function setUp(): void
    {
        $this->checklist = new PdpComplianceChecklist();
    }

    public function testGetChecklistReturnsNonEmptyList(): void
    {
        $items = $this->checklist->getChecklist();

        $this->assertNotEmpty($items);
        $this->assertGreaterThanOrEqual(10, count($items));
    }

    public function testEachItemHasRequiredFields(): void
    {
        $requiredFields = ['id', 'category', 'requirement', 'description', 'status', 'blocking'];

        foreach ($this->checklist->getChecklist() as $item) {
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $item, "L'element {$item['id']} manque le champ {$field}");
            }
        }
    }

    public function testItemIdsAreUnique(): void
    {
        $ids = array_map(
            static fn (array $item): string => $item['id'],
            $this->checklist->getChecklist(),
        );

        $this->assertSame($ids, array_unique($ids));
    }

    public function testAllStatusesAreValid(): void
    {
        $validStatuses = [
            PdpComplianceChecklist::STATUS_DONE,
            PdpComplianceChecklist::STATUS_IN_PROGRESS,
            PdpComplianceChecklist::STATUS_TODO,
            PdpComplianceChecklist::STATUS_MANUAL,
        ];

        foreach ($this->checklist->getChecklist() as $item) {
            $this->assertContains(
                $item['status'],
                $validStatuses,
                "Statut invalide : {$item['status']} pour {$item['id']}",
            );
        }
    }

    public function testAllCategoriesAreValid(): void
    {
        $validCategories = [
            PdpComplianceChecklist::CATEGORY_TECHNICAL,
            PdpComplianceChecklist::CATEGORY_SECURITY,
            PdpComplianceChecklist::CATEGORY_LEGAL,
            PdpComplianceChecklist::CATEGORY_OPERATIONAL,
        ];

        foreach ($this->checklist->getChecklist() as $item) {
            $this->assertContains(
                $item['category'],
                $validCategories,
                "Categorie invalide : {$item['category']} pour {$item['id']}",
            );
        }
    }

    public function testCompletionRateIsBetweenZeroAndHundred(): void
    {
        $rate = $this->checklist->getCompletionRate();

        $this->assertGreaterThanOrEqual(0.0, $rate);
        $this->assertLessThanOrEqual(100.0, $rate);
    }

    public function testCompletionRateReflectsStatus(): void
    {
        $items = $this->checklist->getChecklist();
        $total = count($items);
        $done = count(array_filter(
            $items,
            static fn (array $item): bool => PdpComplianceChecklist::STATUS_DONE === $item['status'],
        ));

        $expectedRate = $total > 0 ? round(($done / $total) * 100, 1) : 0.0;

        $this->assertSame($expectedRate, $this->checklist->getCompletionRate());
    }

    public function testGetBlockingItemsExcludesDone(): void
    {
        $blocking = $this->checklist->getBlockingItems();

        foreach ($blocking as $item) {
            $this->assertTrue($item['blocking']);
            $this->assertNotSame(PdpComplianceChecklist::STATUS_DONE, $item['status']);
        }
    }

    public function testGetManualActionsReturnsCorrectItems(): void
    {
        $manual = $this->checklist->getManualActions();

        $this->assertNotEmpty($manual);
        foreach ($manual as $item) {
            $this->assertSame(PdpComplianceChecklist::STATUS_MANUAL, $item['status']);
        }
    }

    public function testGetSummaryByCategoryCoversAllCategories(): void
    {
        $summary = $this->checklist->getSummaryByCategory();

        $this->assertArrayHasKey(PdpComplianceChecklist::CATEGORY_TECHNICAL, $summary);
        $this->assertArrayHasKey(PdpComplianceChecklist::CATEGORY_SECURITY, $summary);
        $this->assertArrayHasKey(PdpComplianceChecklist::CATEGORY_LEGAL, $summary);
        $this->assertArrayHasKey(PdpComplianceChecklist::CATEGORY_OPERATIONAL, $summary);

        foreach ($summary as $cat) {
            $this->assertArrayHasKey('total', $cat);
            $this->assertArrayHasKey('done', $cat);
            $this->assertArrayHasKey('label', $cat);
            $this->assertGreaterThanOrEqual(0, $cat['done']);
            $this->assertGreaterThanOrEqual($cat['done'], $cat['total']);
        }
    }

    public function testSummaryTotalsMatchChecklist(): void
    {
        $summary = $this->checklist->getSummaryByCategory();
        $totalFromSummary = array_sum(array_column($summary, 'total'));
        $totalFromChecklist = count($this->checklist->getChecklist());

        $this->assertSame($totalFromChecklist, $totalFromSummary);
    }

    public function testTechnicalItemsExist(): void
    {
        $summary = $this->checklist->getSummaryByCategory();

        $this->assertGreaterThanOrEqual(5, $summary[PdpComplianceChecklist::CATEGORY_TECHNICAL]['total']);
    }
}
