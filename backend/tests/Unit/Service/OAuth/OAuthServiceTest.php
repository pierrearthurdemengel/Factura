<?php

namespace App\Tests\Unit\Service\OAuth;

use App\Entity\OAuthAccessToken;
use App\Entity\OAuthAuthorizationCode;
use App\Entity\OAuthClient;
use App\Entity\OAuthRefreshToken;
use App\Entity\User;
use App\Service\OAuth\OAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class OAuthServiceTest extends TestCase
{
    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('dev@test.fr');
        $user->setFirstName('Pierre');
        $user->setLastName('Test');
        $user->setPassword('hashed');

        return $user;
    }

    private function createClient(): OAuthClient
    {
        $client = new OAuthClient();
        $client->setClientId('claude_connector');
        $client->setName('Claude');
        $client->setRedirectUris(['https://claude.ai/callback']);
        $client->setAllowedScopes(['invoices:read', 'invoices:write', 'clients:read']);
        $client->setIsPublic(true);

        return $client;
    }

    private function createMockEm(): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        return $em;
    }

    public function testFindClientReturnsClient(): void
    {
        $client = $this->createClient();
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->with(['clientId' => 'claude_connector'])->willReturn($client);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(OAuthClient::class)->willReturn($repo);

        $service = new OAuthService($em);

        $this->assertSame($client, $service->findClient('claude_connector'));
    }

    public function testFindClientReturnsNullForUnknown(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(OAuthClient::class)->willReturn($repo);

        $service = new OAuthService($em);

        $this->assertNull($service->findClient('unknown'));
    }

    public function testCreateAuthorizationCodeReturnsCode(): void
    {
        $em = $this->createMockEm();
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new OAuthService($em);
        $code = $service->createAuthorizationCode(
            $this->createClient(),
            $this->createUser(),
            'https://claude.ai/callback',
            'invoices:read clients:read',
        );

        $this->assertNotEmpty($code->getCode());
        $this->assertSame('https://claude.ai/callback', $code->getRedirectUri());
        $this->assertContains('invoices:read', $code->getScopes());
        $this->assertContains('clients:read', $code->getScopes());
    }

    public function testCreateAuthorizationCodeWithPkce(): void
    {
        $em = $this->createMockEm();
        $service = new OAuthService($em);

        $code = $service->createAuthorizationCode(
            $this->createClient(),
            $this->createUser(),
            'https://claude.ai/callback',
            'invoices:read',
            'challenge_hash',
            'S256',
        );

        $this->assertSame('challenge_hash', $code->getCodeChallenge());
        $this->assertSame('S256', $code->getCodeChallengeMethod());
    }

    public function testExchangeAuthorizationCodeReturnsTokens(): void
    {
        $client = $this->createClient();
        $user = $this->createUser();

        $authCode = new OAuthAuthorizationCode();
        $authCode->setCode('test_code');
        $authCode->setClient($client);
        $authCode->setUser($user);
        $authCode->setRedirectUri('https://claude.ai/callback');
        $authCode->setScopes(['invoices:read']);

        $codeRepo = $this->createMock(EntityRepository::class);
        $codeRepo->method('findOneBy')->with(['code' => 'test_code'])->willReturn($authCode);

        $em = $this->createMockEm();
        $em->method('getRepository')
            ->with(OAuthAuthorizationCode::class)
            ->willReturn($codeRepo);

        $service = new OAuthService($em);
        $result = $service->exchangeAuthorizationCode(
            'test_code',
            'claude_connector',
            'https://claude.ai/callback',
        );

        $this->assertNotNull($result);
        $this->assertStringStartsWith('mfp_', $result['access_token']);
        $this->assertStringStartsWith('mfp_rt_', $result['refresh_token']);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame(3600, $result['expires_in']);
        $this->assertSame('invoices:read', $result['scope']);
    }

    public function testExchangeAuthorizationCodeFailsWithExpiredCode(): void
    {
        $client = $this->createClient();
        $user = $this->createUser();

        $authCode = new OAuthAuthorizationCode();
        $authCode->setCode('expired_code');
        $authCode->setClient($client);
        $authCode->setUser($user);
        $authCode->setRedirectUri('https://claude.ai/callback');
        $authCode->setScopes(['invoices:read']);

        // Simuler un code expire en le marquant comme utilise
        $authCode->markUsed();

        $codeRepo = $this->createMock(EntityRepository::class);
        $codeRepo->method('findOneBy')->willReturn($authCode);

        $em = $this->createMockEm();
        $em->method('getRepository')
            ->with(OAuthAuthorizationCode::class)
            ->willReturn($codeRepo);

        $service = new OAuthService($em);

        $this->assertNull($service->exchangeAuthorizationCode(
            'expired_code',
            'claude_connector',
            'https://claude.ai/callback',
        ));
    }

    public function testExchangeAuthorizationCodeFailsWithWrongClient(): void
    {
        $client = $this->createClient();
        $user = $this->createUser();

        $authCode = new OAuthAuthorizationCode();
        $authCode->setCode('code');
        $authCode->setClient($client);
        $authCode->setUser($user);
        $authCode->setRedirectUri('https://claude.ai/callback');
        $authCode->setScopes(['invoices:read']);

        $codeRepo = $this->createMock(EntityRepository::class);
        $codeRepo->method('findOneBy')->willReturn($authCode);

        $em = $this->createMockEm();
        $em->method('getRepository')
            ->with(OAuthAuthorizationCode::class)
            ->willReturn($codeRepo);

        $service = new OAuthService($em);

        $this->assertNull($service->exchangeAuthorizationCode(
            'code',
            'wrong_client_id',
            'https://claude.ai/callback',
        ));
    }

    public function testExchangeAuthorizationCodeFailsWithWrongRedirectUri(): void
    {
        $client = $this->createClient();
        $user = $this->createUser();

        $authCode = new OAuthAuthorizationCode();
        $authCode->setCode('code');
        $authCode->setClient($client);
        $authCode->setUser($user);
        $authCode->setRedirectUri('https://claude.ai/callback');
        $authCode->setScopes(['invoices:read']);

        $codeRepo = $this->createMock(EntityRepository::class);
        $codeRepo->method('findOneBy')->willReturn($authCode);

        $em = $this->createMockEm();
        $em->method('getRepository')
            ->with(OAuthAuthorizationCode::class)
            ->willReturn($codeRepo);

        $service = new OAuthService($em);

        $this->assertNull($service->exchangeAuthorizationCode(
            'code',
            'claude_connector',
            'https://evil.com/callback',
        ));
    }

    public function testRefreshAccessTokenReturnsNewTokens(): void
    {
        $client = $this->createClient();
        $user = $this->createUser();

        $oldAccessToken = new OAuthAccessToken();
        $oldAccessToken->setToken('mfp_old');
        $oldAccessToken->setClient($client);
        $oldAccessToken->setUser($user);
        $oldAccessToken->setScopes(['invoices:read']);
        $oldAccessToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $refreshToken = new OAuthRefreshToken();
        $refreshToken->setToken('mfp_rt_test');
        $refreshToken->setAccessToken($oldAccessToken);

        $rtRepo = $this->createMock(EntityRepository::class);
        $rtRepo->method('findOneBy')->with(['token' => 'mfp_rt_test'])->willReturn($refreshToken);

        $em = $this->createMockEm();
        $em->method('getRepository')
            ->with(OAuthRefreshToken::class)
            ->willReturn($rtRepo);

        $service = new OAuthService($em);
        $result = $service->refreshAccessToken('mfp_rt_test');

        $this->assertNotNull($result);
        $this->assertStringStartsWith('mfp_', $result['access_token']);
        $this->assertStringStartsWith('mfp_rt_', $result['refresh_token']);
        $this->assertTrue($oldAccessToken->isRevoked());
        $this->assertTrue($refreshToken->isRevoked());
    }

    public function testRefreshAccessTokenFailsWithRevokedToken(): void
    {
        $client = $this->createClient();
        $user = $this->createUser();

        $oldAccessToken = new OAuthAccessToken();
        $oldAccessToken->setToken('mfp_old');
        $oldAccessToken->setClient($client);
        $oldAccessToken->setUser($user);
        $oldAccessToken->setScopes([]);
        $oldAccessToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $refreshToken = new OAuthRefreshToken();
        $refreshToken->setToken('mfp_rt_revoked');
        $refreshToken->setAccessToken($oldAccessToken);
        $refreshToken->revoke();

        $rtRepo = $this->createMock(EntityRepository::class);
        $rtRepo->method('findOneBy')->willReturn($refreshToken);

        $em = $this->createMockEm();
        $em->method('getRepository')
            ->with(OAuthRefreshToken::class)
            ->willReturn($rtRepo);

        $service = new OAuthService($em);

        $this->assertNull($service->refreshAccessToken('mfp_rt_revoked'));
    }

    public function testFindValidAccessTokenReturnsValidToken(): void
    {
        $client = $this->createClient();
        $user = $this->createUser();

        $token = new OAuthAccessToken();
        $token->setToken('mfp_valid');
        $token->setClient($client);
        $token->setUser($user);
        $token->setScopes(['invoices:read']);
        $token->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->with(['token' => 'mfp_valid'])->willReturn($token);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(OAuthAccessToken::class)->willReturn($repo);

        $service = new OAuthService($em);
        $found = $service->findValidAccessToken('mfp_valid');

        $this->assertSame($token, $found);
        $this->assertNotNull($token->getLastUsedAt());
    }

    public function testFindValidAccessTokenReturnsNullForRevoked(): void
    {
        $token = new OAuthAccessToken();
        $token->setToken('mfp_revoked');
        $token->setClient($this->createClient());
        $token->setUser($this->createUser());
        $token->setScopes([]);
        $token->setExpiresAt(new \DateTimeImmutable('+1 hour'));
        $token->revoke();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($token);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(OAuthAccessToken::class)->willReturn($repo);

        $service = new OAuthService($em);

        $this->assertNull($service->findValidAccessToken('mfp_revoked'));
    }

    public function testRevokeAccessTokenRevokesAssociatedRefreshTokens(): void
    {
        $token = new OAuthAccessToken();
        $token->setToken('mfp_to_revoke');
        $token->setClient($this->createClient());
        $token->setUser($this->createUser());
        $token->setScopes([]);
        $token->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $rt1 = new OAuthRefreshToken();
        $rt1->setToken('mfp_rt_1');
        $rt1->setAccessToken($token);

        $rt2 = new OAuthRefreshToken();
        $rt2->setToken('mfp_rt_2');
        $rt2->setAccessToken($token);

        $rtRepo = $this->createMock(EntityRepository::class);
        $rtRepo->method('findBy')->with(['accessToken' => $token])->willReturn([$rt1, $rt2]);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(OAuthRefreshToken::class)->willReturn($rtRepo);

        $service = new OAuthService($em);
        $service->revokeAccessToken($token);

        $this->assertTrue($token->isRevoked());
        $this->assertTrue($rt1->isRevoked());
        $this->assertTrue($rt2->isRevoked());
    }

    public function testGetActiveIntegrationsFiltersRevokedTokens(): void
    {
        $client = $this->createClient();
        $user = $this->createUser();

        $active = new OAuthAccessToken();
        $active->setToken('mfp_active');
        $active->setClient($client);
        $active->setUser($user);
        $active->setScopes(['invoices:read']);
        $active->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $revoked = new OAuthAccessToken();
        $revoked->setToken('mfp_revoked');
        $revoked->setClient($client);
        $revoked->setUser($user);
        $revoked->setScopes(['invoices:read']);
        $revoked->setExpiresAt(new \DateTimeImmutable('+1 hour'));
        $revoked->revoke();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->with(['user' => $user])->willReturn([$active, $revoked]);

        $em = $this->createMockEm();
        $em->method('getRepository')->with(OAuthAccessToken::class)->willReturn($repo);

        $service = new OAuthService($em);
        $integrations = $service->getActiveIntegrations($user);

        $this->assertCount(1, $integrations);
        $this->assertSame($active, $integrations[0]);
    }

    public function testFilterScopesRemovesInvalidScopes(): void
    {
        $em = $this->createMockEm();
        $service = new OAuthService($em);

        $code = $service->createAuthorizationCode(
            $this->createClient(),
            $this->createUser(),
            'https://claude.ai/callback',
            'invoices:read invalid:scope clients:read admin:all',
        );

        $scopes = $code->getScopes();
        $this->assertContains('invoices:read', $scopes);
        $this->assertContains('clients:read', $scopes);
        $this->assertNotContains('invalid:scope', $scopes);
        $this->assertNotContains('admin:all', $scopes);
    }
}
