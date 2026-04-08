<?php

namespace App\Tests\Unit\Security;

use App\Entity\Company;
use App\Entity\Quote;
use App\Entity\User;
use App\Security\Voter\QuoteVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Uid\Uuid;

class QuoteVoterTest extends TestCase
{
    private QuoteVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new QuoteVoter();
    }

    /**
     * Verifie que le proprietaire peut voir son devis.
     */
    public function testOwnerCanViewQuote(): void
    {
        $companyId = Uuid::v7();
        $result = $this->vote('VIEW', 'DRAFT', $companyId, $companyId);
        $this->assertSame(1, $result);
    }

    /**
     * Verifie qu'un non-proprietaire ne peut pas voir un devis.
     */
    public function testNonOwnerCannotViewQuote(): void
    {
        $result = $this->vote('VIEW', 'DRAFT', Uuid::v7(), Uuid::v7());
        $this->assertSame(-1, $result);
    }

    /**
     * Verifie que le proprietaire peut editer un devis en DRAFT.
     */
    public function testOwnerCanEditDraftQuote(): void
    {
        $companyId = Uuid::v7();
        $result = $this->vote('EDIT', 'DRAFT', $companyId, $companyId);
        $this->assertSame(1, $result);
    }

    /**
     * Verifie qu'un devis SENT ne peut pas etre edite.
     */
    public function testCannotEditSentQuote(): void
    {
        $companyId = Uuid::v7();
        $result = $this->vote('EDIT', 'SENT', $companyId, $companyId);
        $this->assertSame(-1, $result);
    }

    /**
     * Verifie que le proprietaire peut supprimer un devis en DRAFT.
     */
    public function testOwnerCanDeleteDraftQuote(): void
    {
        $companyId = Uuid::v7();
        $result = $this->vote('DELETE', 'DRAFT', $companyId, $companyId);
        $this->assertSame(1, $result);
    }

    /**
     * Verifie que la conversion est possible uniquement depuis ACCEPTED.
     */
    public function testCanConvertAcceptedQuote(): void
    {
        $companyId = Uuid::v7();
        $result = $this->vote('CONVERT', 'ACCEPTED', $companyId, $companyId);
        $this->assertSame(1, $result);
    }

    /**
     * Verifie que la conversion est impossible depuis DRAFT.
     */
    public function testCannotConvertDraftQuote(): void
    {
        $companyId = Uuid::v7();
        $result = $this->vote('CONVERT', 'DRAFT', $companyId, $companyId);
        $this->assertSame(-1, $result);
    }

    /**
     * Verifie que l'envoi est possible uniquement depuis DRAFT avec un devis valide.
     */
    public function testCanSendValidDraftQuote(): void
    {
        $companyId = Uuid::v7();
        $result = $this->vote('SEND', 'DRAFT', $companyId, $companyId, true);
        $this->assertSame(1, $result);
    }

    /**
     * Verifie que l'envoi est impossible si le devis est invalide.
     */
    public function testCannotSendInvalidDraftQuote(): void
    {
        $companyId = Uuid::v7();
        $result = $this->vote('SEND', 'DRAFT', $companyId, $companyId, false);
        $this->assertSame(-1, $result);
    }

    private function vote(
        string $attribute,
        string $status,
        Uuid $companyId,
        Uuid $userCompanyId,
        bool $isValid = true,
    ): int {
        $company = $this->createMock(Company::class);
        $company->method('getId')->willReturn($companyId);

        $userCompany = $this->createMock(Company::class);
        $userCompany->method('getId')->willReturn($userCompanyId);

        $user = $this->createMock(User::class);
        $user->method('getCompany')->willReturn($userCompany);

        $quote = $this->createMock(Quote::class);
        $quote->method('getSeller')->willReturn($company);
        $quote->method('getStatus')->willReturn($status);
        $quote->method('isValid')->willReturn($isValid);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $this->voter->vote($token, $quote, [$attribute]);
    }
}
