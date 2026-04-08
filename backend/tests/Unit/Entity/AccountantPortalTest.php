<?php

namespace App\Tests\Unit\Entity;

use App\Entity\AccountantInvitation;
use App\Entity\AccountantProfile;
use App\Entity\Company;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class AccountantPortalTest extends TestCase
{
    public function testAccountantProfileCreation(): void
    {
        $user = new User();
        $profile = new AccountantProfile();
        $profile->setUser($user);
        $profile->setFirmName('Cabinet Expert & Associes');
        $profile->setFirmSiren('123456789');

        self::assertSame('Cabinet Expert & Associes', $profile->getFirmName());
        self::assertSame('123456789', $profile->getFirmSiren());
        self::assertSame($user, $profile->getUser());
        self::assertSame(0, $profile->getClientCount());
    }

    public function testAddCompanyToProfile(): void
    {
        $profile = new AccountantProfile();
        $profile->setUser(new User());
        $profile->setFirmName('Mon Cabinet');

        $company1 = new Company();
        $company2 = new Company();

        $profile->addCompany($company1);
        $profile->addCompany($company2);

        self::assertSame(2, $profile->getClientCount());
        self::assertTrue($profile->hasCompany($company1));
        self::assertTrue($profile->hasCompany($company2));
    }

    public function testNoDuplicateCompanies(): void
    {
        $profile = new AccountantProfile();
        $profile->setUser(new User());
        $profile->setFirmName('Mon Cabinet');

        $company = new Company();

        $profile->addCompany($company);
        $profile->addCompany($company); // Doublon

        self::assertSame(1, $profile->getClientCount());
    }

    public function testRemoveCompanyFromProfile(): void
    {
        $profile = new AccountantProfile();
        $profile->setUser(new User());
        $profile->setFirmName('Mon Cabinet');

        $company = new Company();
        $profile->addCompany($company);
        self::assertTrue($profile->hasCompany($company));

        $profile->removeCompany($company);
        self::assertFalse($profile->hasCompany($company));
    }

    public function testInvitationCreation(): void
    {
        $profile = new AccountantProfile();
        $profile->setUser(new User());
        $profile->setFirmName('Mon Cabinet');

        $invitation = new AccountantInvitation();
        $invitation->setAccountantProfile($profile);
        $invitation->setEmail('client@example.com');

        self::assertSame('client@example.com', $invitation->getEmail());
        self::assertSame(AccountantInvitation::STATUS_PENDING, $invitation->getStatus());
        self::assertNotEmpty($invitation->getToken());
        self::assertSame(64, strlen($invitation->getToken())); // 32 bytes hex = 64 chars
        self::assertFalse($invitation->isExpired());
        self::assertTrue($invitation->isAcceptable());
    }

    public function testInvitationAcceptance(): void
    {
        $profile = new AccountantProfile();
        $profile->setUser(new User());
        $profile->setFirmName('Mon Cabinet');

        $invitation = new AccountantInvitation();
        $invitation->setAccountantProfile($profile);
        $invitation->setEmail('client@example.com');

        $company = new Company();
        $invitation->accept($company);

        self::assertSame(AccountantInvitation::STATUS_ACCEPTED, $invitation->getStatus());
        self::assertSame($company, $invitation->getCompany());
        self::assertNotNull($invitation->getAcceptedAt());
        self::assertTrue($profile->hasCompany($company));
    }

    public function testAcceptedInvitationCannotBeAcceptedAgain(): void
    {
        $profile = new AccountantProfile();
        $profile->setUser(new User());
        $profile->setFirmName('Mon Cabinet');

        $invitation = new AccountantInvitation();
        $invitation->setAccountantProfile($profile);
        $invitation->setEmail('client@example.com');

        $company = new Company();
        $invitation->accept($company);

        self::assertFalse($invitation->isAcceptable());
    }

    public function testWhiteLabelConfiguration(): void
    {
        $profile = new AccountantProfile();
        $profile->setUser(new User());
        $profile->setFirmName('Cabinet Premium');
        $profile->setPrimaryColor('#0066CC');
        $profile->setCustomDomain('compta.moncabinet.fr');
        $profile->setLogoPath('/uploads/logos/cabinet.png');

        self::assertSame('#0066CC', $profile->getPrimaryColor());
        self::assertSame('compta.moncabinet.fr', $profile->getCustomDomain());
        self::assertSame('/uploads/logos/cabinet.png', $profile->getLogoPath());
    }
}
