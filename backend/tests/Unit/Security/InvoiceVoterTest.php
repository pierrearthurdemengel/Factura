<?php

namespace App\Tests\Unit\Security;

use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\User;
use App\Security\Voter\InvoiceVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Uid\Uuid;

class InvoiceVoterTest extends TestCase
{
    private InvoiceVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new InvoiceVoter();
    }

    /**
     * Verifie que le proprietaire peut voir sa facture.
     */
    public function testOwnerCanViewInvoice(): void
    {
        [$invoice, $token] = $this->createInvoiceWithOwner('DRAFT');
        $result = $this->voter->vote($token, $invoice, [InvoiceVoter::VIEW]);
        $this->assertSame(1, $result);
    }

    /**
     * Verifie que le proprietaire peut modifier une facture DRAFT.
     */
    public function testOwnerCanEditDraftInvoice(): void
    {
        [$invoice, $token] = $this->createInvoiceWithOwner('DRAFT');
        $result = $this->voter->vote($token, $invoice, [InvoiceVoter::EDIT]);
        $this->assertSame(1, $result);
    }

    /**
     * Verifie qu'on ne peut pas modifier une facture SENT.
     */
    public function testCannotEditSentInvoice(): void
    {
        [$invoice, $token] = $this->createInvoiceWithOwner('SENT');
        $result = $this->voter->vote($token, $invoice, [InvoiceVoter::EDIT]);
        $this->assertSame(-1, $result);
    }

    /**
     * Verifie qu'on ne peut pas supprimer une facture PAID.
     */
    public function testCannotDeletePaidInvoice(): void
    {
        [$invoice, $token] = $this->createInvoiceWithOwner('PAID');
        $result = $this->voter->vote($token, $invoice, [InvoiceVoter::DELETE]);
        $this->assertSame(-1, $result);
    }

    /**
     * Verifie qu'on peut annuler une facture SENT.
     */
    public function testCanCancelSentInvoice(): void
    {
        [$invoice, $token] = $this->createInvoiceWithOwner('SENT');
        $result = $this->voter->vote($token, $invoice, [InvoiceVoter::CANCEL]);
        $this->assertSame(1, $result);
    }

    /**
     * Verifie qu'on ne peut pas annuler une facture PAID.
     */
    public function testCannotCancelPaidInvoice(): void
    {
        [$invoice, $token] = $this->createInvoiceWithOwner('PAID');
        $result = $this->voter->vote($token, $invoice, [InvoiceVoter::CANCEL]);
        $this->assertSame(-1, $result);
    }

    /**
     * Verifie qu'un utilisateur etranger ne peut rien faire.
     */
    public function testNonOwnerCannotAccessInvoice(): void
    {
        $companyId = Uuid::v4();

        $company = $this->createMock(Company::class);
        $company->method('getId')->willReturn($companyId);

        $user = $this->createMock(User::class);
        $user->method('getCompany')->willReturn($company);

        $otherCompanyId = Uuid::v4();
        $otherCompany = $this->createMock(Company::class);
        $otherCompany->method('getId')->willReturn($otherCompanyId);

        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getSeller')->willReturn($otherCompany);
        $invoice->method('getStatus')->willReturn('DRAFT');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $invoice, [InvoiceVoter::VIEW]);
        $this->assertSame(-1, $result);
    }

    /**
     * @return array{0: Invoice, 1: TokenInterface}
     */
    private function createInvoiceWithOwner(string $status): array
    {
        $companyId = Uuid::v4();

        $company = $this->createMock(Company::class);
        $company->method('getId')->willReturn($companyId);

        $user = $this->createMock(User::class);
        $user->method('getCompany')->willReturn($company);

        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getSeller')->willReturn($company);
        $invoice->method('getStatus')->willReturn($status);
        $invoice->method('isValid')->willReturn(true);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return [$invoice, $token];
    }
}
