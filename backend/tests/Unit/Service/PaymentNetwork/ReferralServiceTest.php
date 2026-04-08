<?php

namespace App\Tests\Unit\Service\PaymentNetwork;

use App\Entity\Referral;
use App\Entity\User;
use App\Service\PaymentNetwork\ReferralService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class ReferralServiceTest extends TestCase
{
    private function createUser(string $email = 'dev@test.fr'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Pierre');
        $user->setLastName('Test');
        $user->setPassword('hashed');

        return $user;
    }

    private function createMockEm(): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        return $em;
    }

    public function testGetOrCreateReferralCodeCreatesNewCode(): void
    {
        $user = $this->createUser();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(Referral::class)->willReturn($repo);
        $em->expects($this->once())->method('persist');

        $service = new ReferralService($em);
        $code = $service->getOrCreateReferralCode($user);

        $this->assertStringStartsWith('MFP-', $code);
        $this->assertSame(10, strlen($code));
    }

    public function testGetOrCreateReferralCodeReusesExisting(): void
    {
        $user = $this->createUser();

        $existing = new Referral();
        $existing->setReferrer($user);
        $existing->setCode('MFP-ABCDEF');

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(Referral::class)->willReturn($repo);
        $em->expects($this->never())->method('persist');

        $service = new ReferralService($em);

        $this->assertSame('MFP-ABCDEF', $service->getOrCreateReferralCode($user));
    }

    public function testCompleteReferralSucceeds(): void
    {
        $referrer = $this->createUser('referrer@test.fr');
        $referee = $this->createUser('referee@test.fr');

        $referral = new Referral();
        $referral->setReferrer($referrer);
        $referral->setCode('MFP-ABCDEF');

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($referral);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(Referral::class)->willReturn($repo);

        $service = new ReferralService($em);
        $result = $service->completeReferral('MFP-ABCDEF', $referee);

        $this->assertNotNull($result);
        $this->assertTrue($result->isCompleted());
        $this->assertSame($referee, $result->getReferee());
    }

    public function testCompleteReferralFailsWithInvalidCode(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(Referral::class)->willReturn($repo);

        $service = new ReferralService($em);

        $this->assertNull($service->completeReferral('INVALID', $this->createUser()));
    }

    public function testRewardReferralSucceeds(): void
    {
        $referrer = $this->createUser('referrer@test.fr');
        $referee = $this->createUser('referee@test.fr');

        $referral = new Referral();
        $referral->setReferrer($referrer);
        $referral->setCode('MFP-ABCDEF');
        $referral->complete($referee);

        $em = $this->createMockEm();

        $service = new ReferralService($em);
        $result = $service->rewardReferral($referral);

        $this->assertTrue($result);
        $this->assertTrue($referral->isRewarded());
        $this->assertNotNull($referral->getRewardedAt());
    }

    public function testRewardReferralFailsIfAlreadyRewarded(): void
    {
        $referrer = $this->createUser('referrer@test.fr');
        $referee = $this->createUser('referee@test.fr');

        $referral = new Referral();
        $referral->setReferrer($referrer);
        $referral->setCode('MFP-ABCDEF');
        $referral->complete($referee);
        $referral->reward();

        $em = $this->createMockEm();

        $service = new ReferralService($em);

        $this->assertFalse($service->rewardReferral($referral));
    }

    public function testRewardReferralFailsIfPending(): void
    {
        $referral = new Referral();
        $referral->setReferrer($this->createUser());
        $referral->setCode('MFP-ABCDEF');

        $em = $this->createMockEm();

        $service = new ReferralService($em);

        $this->assertFalse($service->rewardReferral($referral));
    }

    public function testGenerateCodeFormat(): void
    {
        $code = Referral::generateCode();

        $this->assertStringStartsWith('MFP-', $code);
        $this->assertSame(10, strlen($code));
        // Le code est en majuscules hexadecimales apres le prefixe
        $this->assertMatchesRegularExpression('/^MFP-[A-F0-9]{6}$/', $code);
    }

    public function testReferralRewardMonths(): void
    {
        $this->assertSame(1, Referral::REWARD_MONTHS);
    }
}
