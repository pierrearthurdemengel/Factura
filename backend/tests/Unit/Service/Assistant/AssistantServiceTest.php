<?php

namespace App\Tests\Unit\Service\Assistant;

use App\Entity\Company;
use App\Entity\User;
use App\Service\Assistant\AssistantCacheService;
use App\Service\Assistant\AssistantService;
use App\Service\Assistant\FiscalKnowledgeBase;
use App\Service\Assistant\LlmClient;
use App\Service\Assistant\TaxSimulator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class AssistantServiceTest extends TestCase
{
    private FiscalKnowledgeBase $kb;
    private TaxSimulator $simulator;

    protected function setUp(): void
    {
        $this->kb = new FiscalKnowledgeBase();
        $this->simulator = new TaxSimulator();
    }

    /**
     * Cree un service assistant avec les mocks necessaires.
     *
     * @param array<string, mixed>|null $cachedResponse Reponse en cache
     * @param array<string, mixed>|null $llmResponse    Reponse LLM
     */
    private function createService(?array $cachedResponse = null, ?array $llmResponse = null): AssistantService
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn(null);
        $repo->method('findBy')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $cacheService = $this->createMock(AssistantCacheService::class);
        $cacheService->method('get')->willReturn($cachedResponse);

        $llmClient = $this->createMock(LlmClient::class);
        if (null !== $llmResponse) {
            $llmClient->method('ask')->willReturn($llmResponse);
        } else {
            $llmClient->method('ask')->willReturn([
                'answer' => 'Reponse LLM par defaut.',
                'references' => [],
                'actions' => [],
                'model' => 'haiku',
            ]);
        }

        return new AssistantService($em, $this->kb, $cacheService, $llmClient, $this->simulator);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@test.fr');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');

        return $user;
    }

    private function createCompany(): Company
    {
        $company = new Company();
        $company->setName('Entreprise Test');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        return $company;
    }

    public function testAskReturnsCachedResponse(): void
    {
        $cached = [
            'answer' => 'Reponse en cache',
            'references' => ['Article 278 du CGI'],
            'category' => FiscalKnowledgeBase::CATEGORY_TVA,
            'actions' => [],
        ];

        $service = $this->createService($cached);
        $result = $service->ask($this->createUser(), $this->createCompany(), 'taux tva');

        self::assertSame('Reponse en cache', $result['answer']);
        self::assertSame('cache', $result['source']);
    }

    public function testAskReturnsKnowledgeBaseAnswer(): void
    {
        // Pas de cache, mais la KB a une reponse
        $service = $this->createService(null);
        $result = $service->ask($this->createUser(), $this->createCompany(), 'quel taux tva en france');

        self::assertSame('knowledge_base', $result['source']);
        self::assertStringContainsString('20%', $result['answer']);
    }

    public function testAskFallsBackToLlm(): void
    {
        $llmResponse = [
            'answer' => 'Reponse du LLM.',
            'references' => ['Article 123 du CGI'],
            'actions' => ['Consulter un expert'],
            'model' => 'haiku',
        ];

        $service = $this->createService(null, $llmResponse);
        // Question qui n'est pas dans la KB
        $result = $service->ask($this->createUser(), $this->createCompany(), 'question tres specifique inconnue');

        self::assertSame('haiku', $result['source']);
        self::assertSame('Reponse du LLM.', $result['answer']);
    }

    public function testAskReturnsConversationId(): void
    {
        $service = $this->createService(null);
        $result = $service->ask($this->createUser(), $this->createCompany(), 'taux tva');

        self::assertArrayHasKey('conversationId', $result);
        self::assertNotEmpty($result['conversationId']);
    }

    public function testAskReturnsCategory(): void
    {
        $service = $this->createService(null);
        $result = $service->ask($this->createUser(), $this->createCompany(), 'franchise tva seuil');

        self::assertSame(FiscalKnowledgeBase::CATEGORY_TVA, $result['category']);
    }

    public function testAskDetectsSimulationMicroVsReel(): void
    {
        $service = $this->createService(null);
        $result = $service->ask(
            $this->createUser(),
            $this->createCompany(),
            'simuler micro vs reel pour mon activite',
            null,
            ['turnover' => '50000', 'expenses' => '15000', 'activityType' => 'bnc'],
        );

        self::assertSame('simulation', $result['source']);
        self::assertStringContainsString('Micro', $result['answer']);
        self::assertStringContainsString('Reel', $result['answer']);
    }

    public function testAskDetectsSimulationEiVsSociete(): void
    {
        $service = $this->createService(null);
        $result = $service->ask(
            $this->createUser(),
            $this->createCompany(),
            'simuler passage societe',
            null,
            ['turnover' => '80000', 'expenses' => '20000', 'salary' => '30000'],
        );

        self::assertSame('simulation', $result['source']);
        self::assertStringContainsString('Entreprise individuelle', $result['answer']);
        self::assertStringContainsString('Societe', $result['answer']);
    }

    public function testAskDetectsSimulationIr(): void
    {
        $service = $this->createService(null);
        $result = $service->ask(
            $this->createUser(),
            $this->createCompany(),
            'estimer impot sur le revenu',
            null,
            ['taxableIncome' => '40000', 'parts' => 1],
        );

        self::assertSame('simulation', $result['source']);
        self::assertStringContainsString('impot sur le revenu', $result['answer']);
    }

    public function testGetConversationHistoryEmpty(): void
    {
        $service = $this->createService(null);
        $history = $service->getConversationHistory('nonexistent-id');

        self::assertEmpty($history);
    }

    public function testListConversationsEmpty(): void
    {
        $service = $this->createService(null);
        $list = $service->listConversations($this->createUser(), $this->createCompany());

        self::assertEmpty($list);
    }

    public function testAskResponseContainsReferences(): void
    {
        $service = $this->createService(null);
        $result = $service->ask($this->createUser(), $this->createCompany(), 'quel taux tva en france');

        self::assertArrayHasKey('references', $result);
        self::assertNotEmpty($result['references']);
    }

    public function testAskResponseContainsActions(): void
    {
        $service = $this->createService(null);
        $result = $service->ask($this->createUser(), $this->createCompany(), 'plafond micro entrepreneur');

        self::assertArrayHasKey('actions', $result);
    }

    public function testAskSimulationHasReferences(): void
    {
        $service = $this->createService(null);
        $result = $service->ask(
            $this->createUser(),
            $this->createCompany(),
            'simuler micro vs reel pour mon activite',
        );

        self::assertNotEmpty($result['references']);
    }
}
