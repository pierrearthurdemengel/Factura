<?php

namespace App\Tests\Unit\Service\Billing;

use App\Entity\BillingPlan;
use App\Service\Billing\RevenueBasedBilling;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RevenueBasedBillingTest extends TestCase
{
    private RevenueBasedBilling $service;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->service = new RevenueBasedBilling($em);
    }

    public function testSimulateBelowThresholdReturnsFreedom(): void
    {
        $result = $this->service->simulate('30000.00');

        $this->assertSame('0.00', $result['fee']);
        $this->assertSame('0.0000', $result['effectiveRate']);
        $this->assertSame('0.00', $result['monthlyEquivalent']);
    }

    public function testSimulateAtThresholdReturnsFreedom(): void
    {
        $result = $this->service->simulate('50000.00');

        $this->assertSame('0.00', $result['fee']);
    }

    public function testSimulateAboveThresholdCalculatesCorrectly(): void
    {
        // CA = 80 000 EUR → taxable = 30 000 EUR → 0.1% = 30 EUR
        $result = $this->service->simulate('80000.00');

        $this->assertSame('30.00', $result['fee']);
        $this->assertSame('0.0375', $result['effectiveRate']);
        $this->assertSame('2.50', $result['monthlyEquivalent']);
    }

    public function testSimulateHighRevenueHitsCapAt588(): void
    {
        // CA = 1 000 000 EUR → taxable = 950 000 → 0.1% = 950 EUR → plafond 588 EUR
        $result = $this->service->simulate('1000000.00');

        $this->assertSame('588.00', $result['fee']);
    }

    public function testSimulateRevenueJustBelowCap(): void
    {
        // Seuil cap : fee = 588 quand taxable = 588000, soit CA = 638 000
        // CA = 600 000 → taxable = 550 000 → 0.1% = 550 EUR (< 588)
        $result = $this->service->simulate('600000.00');

        $this->assertSame('550.00', $result['fee']);
    }

    public function testSimulateZeroRevenue(): void
    {
        $result = $this->service->simulate('0.00');

        $this->assertSame('0.00', $result['fee']);
        $this->assertSame('0.00', $result['effectiveRate']);
    }

    public function testCabinetFeeWithIncludedClients(): void
    {
        // 20 clients inclus → 79 EUR/mois
        $result = $this->service->calculateCabinetFee(20);

        $this->assertSame('79.00', $result['monthlyFee']);
        $this->assertSame('3.95', $result['perClientCost']);
        $this->assertSame('948.00', $result['annualFee']);
    }

    public function testCabinetFeeWithExtraClients(): void
    {
        // 150 clients → 79 + (130 * 2) = 339 EUR/mois
        $result = $this->service->calculateCabinetFee(150);

        $this->assertSame('339.00', $result['monthlyFee']);
        $this->assertSame('2.26', $result['perClientCost']);
        $this->assertSame('4068.00', $result['annualFee']);
    }

    public function testCabinetFeeWithZeroClients(): void
    {
        $result = $this->service->calculateCabinetFee(0);

        $this->assertSame('79.00', $result['monthlyFee']);
        $this->assertSame('0.00', $result['perClientCost']);
    }

    public function testPlanConstants(): void
    {
        $this->assertSame('50000.00', BillingPlan::SUCCESS_THRESHOLD);
        $this->assertSame('0.001', BillingPlan::SUCCESS_RATE);
        $this->assertSame('588.00', BillingPlan::SUCCESS_CAP_ANNUAL);
        $this->assertSame('14.00', BillingPlan::PRICE_PRO_MONTHLY);
        $this->assertSame('79.00', BillingPlan::PRICE_CABINET_BASE);
        $this->assertSame('2.00', BillingPlan::PRICE_CABINET_PER_CLIENT);
        $this->assertSame(20, BillingPlan::CABINET_INCLUDED_CLIENTS);
    }

    public function testPlanTypes(): void
    {
        $this->assertSame('free', BillingPlan::TYPE_FREE);
        $this->assertSame('fixed', BillingPlan::TYPE_FIXED);
        $this->assertSame('success_based', BillingPlan::TYPE_SUCCESS_BASED);
        $this->assertSame('cabinet', BillingPlan::TYPE_CABINET);
    }
}
