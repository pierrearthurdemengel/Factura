<?php

namespace App\Tests\Unit\Service\Factoring;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\FactoringRequest;
use App\Entity\Invoice;
use App\Service\Factoring\FactoringEligibilityChecker;
use App\Service\Factoring\FactoringRequestService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FactoringRequestServiceTest extends TestCase
{
    private FactoringEligibilityChecker&MockObject $eligibilityChecker;
    private EntityManagerInterface&MockObject $em;
    private FactoringRequestService $service;

    protected function setUp(): void
    {
        $this->eligibilityChecker = $this->createMock(FactoringEligibilityChecker::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new FactoringRequestService(
            $this->eligibilityChecker,
            $this->em,
            new NullLogger(),
        );
    }

    public function testRequestFinancingCreatesFactoringRequest(): void
    {
        $invoice = $this->createInvoice();

        $this->eligibilityChecker->method('check')->willReturn([
            'eligible' => true,
            'reason' => null,
            'proposedAmount' => 100000,
            'estimatedFee' => 2500,
            'netAmount' => 96500,
            'estimatedPayoutDays' => 2,
            'clientScore' => 80,
            'commission' => 1000,
        ]);

        $this->em->expects(self::exactly(2))->method('persist'); // FactoringRequest + FactoringEvent
        $this->em->expects(self::once())->method('flush');

        $request = $this->service->requestFinancing($invoice, 'defacto');

        self::assertSame(FactoringRequest::STATUS_PENDING, $request->getStatus());
        self::assertSame(100000, $request->getAmount());
        self::assertSame(2500, $request->getFee());
        self::assertSame(1000, $request->getCommission());
        self::assertSame(80, $request->getClientScore());
        self::assertSame('defacto', $request->getPartnerId());
    }

    public function testRequestFinancingRejectsInvalidPartner(): void
    {
        $invoice = $this->createInvoice();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Partenaire invalide/');

        $this->service->requestFinancing($invoice, 'invalid_partner');
    }

    public function testRequestFinancingRejectsIneligibleInvoice(): void
    {
        $invoice = $this->createInvoice();

        $this->eligibilityChecker->method('check')->willReturn([
            'eligible' => false,
            'reason' => 'Score du client trop bas.',
            'proposedAmount' => null,
            'estimatedFee' => null,
            'netAmount' => null,
            'estimatedPayoutDays' => null,
            'clientScore' => 30,
            'commission' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Score du client/');

        $this->service->requestFinancing($invoice, 'defacto');
    }

    public function testHandleWebhookApproval(): void
    {
        $request = new FactoringRequest();
        $request->setPartnerId('defacto');
        $request->setPartnerReferenceId('REF-123');
        $request->setAmount(100000);
        $request->setFee(2500);
        $request->setClientScore(80);
        $request->setInvoice($this->createInvoice());
        $request->setCompany(new Company());

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($request);
        $this->em->method('getRepository')
            ->with(FactoringRequest::class)
            ->willReturn($repository);

        $this->em->expects(self::exactly(2))->method('persist'); // Webhook event + Approval event
        $this->em->expects(self::once())->method('flush');

        $this->service->handleWebhook('defacto', [
            'event' => 'factoring_approved',
            'referenceId' => 'REF-123',
        ]);

        self::assertSame(FactoringRequest::STATUS_APPROVED, $request->getStatus());
        self::assertNotNull($request->getApprovedAt());
    }

    public function testHandleWebhookRejection(): void
    {
        $request = new FactoringRequest();
        $request->setPartnerId('silvr');
        $request->setPartnerReferenceId('REF-456');
        $request->setAmount(100000);
        $request->setFee(2500);
        $request->setClientScore(80);
        $request->setInvoice($this->createInvoice());
        $request->setCompany(new Company());

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($request);
        $this->em->method('getRepository')
            ->with(FactoringRequest::class)
            ->willReturn($repository);

        $this->service->handleWebhook('silvr', [
            'event' => 'factoring_rejected',
            'referenceId' => 'REF-456',
            'reason' => 'Risque trop eleve',
        ]);

        self::assertSame(FactoringRequest::STATUS_REJECTED, $request->getStatus());
        self::assertSame('Risque trop eleve', $request->getRejectionReason());
    }

    public function testHandleWebhookPayment(): void
    {
        $request = new FactoringRequest();
        $request->setPartnerId('aria');
        $request->setPartnerReferenceId('REF-789');
        $request->setStatus(FactoringRequest::STATUS_APPROVED);
        $request->setAmount(200000);
        $request->setFee(5000);
        $request->setClientScore(90);
        $request->setInvoice($this->createInvoice());
        $request->setCompany(new Company());

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($request);
        $this->em->method('getRepository')
            ->with(FactoringRequest::class)
            ->willReturn($repository);

        $this->service->handleWebhook('aria', [
            'event' => 'funds_transferred',
            'referenceId' => 'REF-789',
        ]);

        self::assertSame(FactoringRequest::STATUS_PAID, $request->getStatus());
        self::assertNotNull($request->getPaidAt());
    }

    public function testHandleWebhookIgnoresUnknownReference(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')
            ->with(FactoringRequest::class)
            ->willReturn($repository);

        // Ne doit pas lever d'exception
        $this->em->expects(self::never())->method('flush');

        $this->service->handleWebhook('defacto', [
            'event' => 'factoring_approved',
            'referenceId' => 'UNKNOWN-REF',
        ]);
    }

    public function testCancelPendingRequest(): void
    {
        $request = new FactoringRequest();
        $request->setPartnerId('defacto');
        $request->setAmount(100000);
        $request->setFee(2500);
        $request->setClientScore(80);
        $request->setInvoice($this->createInvoice());
        $request->setCompany(new Company());

        self::assertTrue($request->isCancellable());

        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $this->service->cancelRequest($request);

        self::assertSame(FactoringRequest::STATUS_CANCELLED, $request->getStatus());
    }

    public function testCannotCancelApprovedRequest(): void
    {
        $request = new FactoringRequest();
        $request->setStatus(FactoringRequest::STATUS_APPROVED);
        $request->setPartnerId('defacto');
        $request->setAmount(100000);
        $request->setFee(2500);
        $request->setClientScore(80);
        $request->setInvoice($this->createInvoice());
        $request->setCompany(new Company());

        $this->expectException(\LogicException::class);

        $this->service->cancelRequest($request);
    }

    public function testAllowedPartnersListIsComplete(): void
    {
        $partners = FactoringRequestService::getAllowedPartners();

        self::assertContains('defacto', $partners);
        self::assertContains('silvr', $partners);
        self::assertContains('aria', $partners);
        self::assertContains('hokodo', $partners);
        self::assertCount(4, $partners);
    }

    private function createInvoice(): Invoice
    {
        $company = new Company();
        $client = new Client();

        $nameRef = new \ReflectionProperty(Client::class, 'name');
        $nameRef->setValue($client, 'Client Test');

        $companyRef = new \ReflectionProperty(Client::class, 'company');
        $companyRef->setValue($client, $company);

        $invoice = new Invoice();
        $invoice->setStatus('SENT');
        $invoice->setTotalIncludingTax('1000.00');
        $invoice->setSeller($company);
        $invoice->setBuyer($client);
        $invoice->setIssueDate(new \DateTimeImmutable('-10 days'));
        $invoice->setDueDate(new \DateTimeImmutable('+20 days'));

        return $invoice;
    }
}
