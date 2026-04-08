<?php

namespace App\Tests\Unit\Service\Reminder;

use App\Entity\Company;
use App\Entity\ReminderTemplate;
use App\Entity\User;
use App\Service\Reminder\ReminderTemplateProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du fournisseur de templates de relance.
 *
 * Verifie la recuperation des templates personnalises et
 * le fallback vers les templates par defaut.
 */
class ReminderTemplateProviderTest extends TestCase
{
    /**
     * Verifie que le template personnalise est retourne s'il existe.
     */
    public function testReturnsCustomTemplateWhenAvailable(): void
    {
        $company = $this->createCompany();
        $customTemplate = new ReminderTemplate();
        $customTemplate->setCompany($company);
        $customTemplate->setType(ReminderTemplate::TYPE_FIRST_REMINDER);
        $customTemplate->setSubject('Mon sujet personnalise');
        $customTemplate->setBody('Mon corps personnalise');

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('findOneBy')
            ->with(['company' => $company, 'type' => ReminderTemplate::TYPE_FIRST_REMINDER])
            ->willReturn($customTemplate);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $provider = new ReminderTemplateProvider($em);
        $result = $provider->getTemplate($company, ReminderTemplate::TYPE_FIRST_REMINDER);

        $this->assertSame('Mon sujet personnalise', $result->getSubject());
    }

    /**
     * Verifie le fallback vers un template par defaut.
     */
    public function testReturnsDefaultTemplateWhenNoCustom(): void
    {
        $company = $this->createCompany();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $provider = new ReminderTemplateProvider($em);
        $result = $provider->getTemplate($company, ReminderTemplate::TYPE_BEFORE_DUE);

        $this->assertStringContainsString('echeance', $result->getSubject());
        $this->assertSame(ReminderTemplate::TYPE_BEFORE_DUE, $result->getType());
    }

    /**
     * Verifie le template par defaut pour la premiere relance.
     */
    public function testDefaultFirstReminderTemplate(): void
    {
        $company = $this->createCompany();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $provider = new ReminderTemplateProvider($em);
        $result = $provider->getTemplate($company, ReminderTemplate::TYPE_FIRST_REMINDER);

        $this->assertStringContainsString('echue', $result->getSubject());
        $this->assertStringContainsString('reglement', $result->getBody());
    }

    /**
     * Verifie le template par defaut pour la mise en demeure.
     */
    public function testDefaultFormalNoticeTemplate(): void
    {
        $company = $this->createCompany();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $provider = new ReminderTemplateProvider($em);
        $result = $provider->getTemplate($company, ReminderTemplate::TYPE_FORMAL_NOTICE);

        $this->assertStringContainsString('Mise en demeure', $result->getSubject());
        $this->assertStringContainsString('L.441-10', $result->getBody());
    }

    /**
     * Verifie que les variables sont correctement interpolees.
     */
    public function testTemplateVariableInterpolation(): void
    {
        $template = new ReminderTemplate();
        $template->setSubject('Relance : facture {numero}');
        $template->setBody('Bonjour {client}, la facture {numero} de {montant} est echue.');
        $template->setType(ReminderTemplate::TYPE_FIRST_REMINDER);

        $variables = [
            'client' => 'Acme Corp',
            'numero' => 'FA-2026-0001',
            'montant' => '1500.00 EUR',
            'echeance' => '01/04/2026',
            'entreprise' => 'Ma Boite SAS',
        ];

        $subject = $template->renderSubject($variables);
        $body = $template->render($variables);

        $this->assertSame('Relance : facture FA-2026-0001', $subject);
        $this->assertStringContainsString('Acme Corp', $body);
        $this->assertStringContainsString('FA-2026-0001', $body);
        $this->assertStringContainsString('1500.00 EUR', $body);
    }

    private function createCompany(): Company
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('hashed');

        $company = new Company();
        $company->setOwner($user);
        $company->setName('Test SAS');
        $company->setSiren('123456789');
        $company->setLegalForm('SAS');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        return $company;
    }
}
