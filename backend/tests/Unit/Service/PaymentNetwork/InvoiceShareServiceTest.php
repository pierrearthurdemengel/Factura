<?php

namespace App\Tests\Unit\Service\PaymentNetwork;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceShareLink;
use App\Service\PaymentNetwork\InvoiceShareService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class InvoiceShareServiceTest extends TestCase
{
    private function createInvoice(): Invoice
    {
        $seller = new Company();
        $seller->setName('Mon Entreprise');
        $seller->setSiren('123456789');
        $seller->setLegalForm('SARL');
        $seller->setAddressLine1('1 rue Test');
        $seller->setPostalCode('75001');
        $seller->setCity('Paris');

        $buyer = new Client();
        $buyer->setName('Client Test');
        $buyer->setAddressLine1('2 rue Client');
        $buyer->setPostalCode('69001');
        $buyer->setCity('Lyon');
        $buyer->setSiren('987654321');
        $buyer->setCompany($seller);

        $invoice = new Invoice();
        $invoice->setSeller($seller);
        $invoice->setBuyer($buyer);
        $invoice->setNumber('FA-2026-0001');

        return $invoice;
    }

    private function createMockEm(): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        return $em;
    }

    public function testCreateShareLinkReturnsNewLink(): void
    {
        $invoice = $this->createInvoice();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(InvoiceShareLink::class)->willReturn($repo);
        $em->expects($this->once())->method('persist');

        $service = new InvoiceShareService($em);
        $link = $service->createShareLink($invoice);

        $this->assertNotEmpty($link->getToken());
        $this->assertSame($invoice, $link->getInvoice());
        $this->assertFalse($link->isExpired());
    }

    public function testCreateShareLinkReusesExistingActiveLink(): void
    {
        $invoice = $this->createInvoice();

        $existingLink = new InvoiceShareLink();
        $existingLink->setInvoice($invoice);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($existingLink);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(InvoiceShareLink::class)->willReturn($repo);
        $em->expects($this->never())->method('persist');

        $service = new InvoiceShareService($em);
        $link = $service->createShareLink($invoice);

        $this->assertSame($existingLink, $link);
    }

    public function testCreateShareLinkWithReferralCode(): void
    {
        $invoice = $this->createInvoice();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(InvoiceShareLink::class)->willReturn($repo);

        $service = new InvoiceShareService($em);
        $link = $service->createShareLink($invoice, 'MFP-ABC123');

        $this->assertSame('MFP-ABC123', $link->getReferralCode());
    }

    public function testViewInvoiceMarksAsViewed(): void
    {
        $invoice = $this->createInvoice();
        $link = new InvoiceShareLink();
        $link->setInvoice($invoice);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($link);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(InvoiceShareLink::class)->willReturn($repo);

        $service = new InvoiceShareService($em);
        $result = $service->viewInvoice($link->getToken());

        $this->assertNotNull($result);
        $this->assertSame($invoice, $result['invoice']);
        $this->assertNotNull($link->getViewedAt());
        $this->assertSame(1, $link->getViewCount());
    }

    public function testViewInvoiceReturnsNullForExpiredLink(): void
    {
        $link = new InvoiceShareLink();
        $link->setInvoice($this->createInvoice());
        $link->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($link);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(InvoiceShareLink::class)->willReturn($repo);

        $service = new InvoiceShareService($em);

        $this->assertNull($service->viewInvoice($link->getToken()));
    }

    public function testViewInvoiceReturnsNullForUnknownToken(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(InvoiceShareLink::class)->willReturn($repo);

        $service = new InvoiceShareService($em);

        $this->assertNull($service->viewInvoice('unknown_token'));
    }

    public function testAcknowledgeReceiptSucceeds(): void
    {
        $link = new InvoiceShareLink();
        $link->setInvoice($this->createInvoice());

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($link);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(InvoiceShareLink::class)->willReturn($repo);

        $service = new InvoiceShareService($em);
        $result = $service->acknowledgeReceipt($link->getToken());

        $this->assertTrue($result);
        $this->assertTrue($link->isAcknowledged());
    }

    public function testAcknowledgeReceiptFailsIfAlreadyAcknowledged(): void
    {
        $link = new InvoiceShareLink();
        $link->setInvoice($this->createInvoice());
        $link->acknowledge();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($link);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(InvoiceShareLink::class)->willReturn($repo);

        $service = new InvoiceShareService($em);

        $this->assertFalse($service->acknowledgeReceipt($link->getToken()));
    }

    public function testDetectIntraNetworkBuyerReturnsCompanyWhenSirenMatches(): void
    {
        $invoice = $this->createInvoice();

        $matchingCompany = new Company();
        $matchingCompany->setName('Client Company');
        $matchingCompany->setSiren('987654321');
        $matchingCompany->setLegalForm('SAS');
        $matchingCompany->setAddressLine1('3 rue Match');
        $matchingCompany->setPostalCode('75002');
        $matchingCompany->setCity('Paris');

        $companyRepo = $this->createMock(EntityRepository::class);
        $companyRepo->method('findOneBy')
            ->with(['siren' => '987654321'])
            ->willReturn($matchingCompany);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(Company::class)->willReturn($companyRepo);

        $service = new InvoiceShareService($em);
        $result = $service->detectIntraNetworkBuyer($invoice);

        $this->assertSame($matchingCompany, $result);
    }

    public function testDetectIntraNetworkBuyerReturnsNullWhenNoMatch(): void
    {
        $invoice = $this->createInvoice();

        $companyRepo = $this->createMock(EntityRepository::class);
        $companyRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(Company::class)->willReturn($companyRepo);

        $service = new InvoiceShareService($em);

        $this->assertNull($service->detectIntraNetworkBuyer($invoice));
    }

    public function testMultipleViewsIncrementViewCount(): void
    {
        $link = new InvoiceShareLink();
        $link->setInvoice($this->createInvoice());

        $link->markViewed();
        $link->markViewed();
        $link->markViewed();

        $this->assertSame(3, $link->getViewCount());
        // La date de premiere vue est conservee
        $this->assertNotNull($link->getViewedAt());
    }
}
