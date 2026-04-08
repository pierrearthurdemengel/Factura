<?php

namespace App\Tests\Unit\Service\Reminder;

use App\Entity\ReminderConfig;
use App\Entity\ReminderTemplate;
use App\Service\Reminder\ReminderScheduler;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du planificateur de relances.
 *
 * Verifie la detection des factures a relancer selon les delais
 * configures et la date d'echeance.
 */
class ReminderSchedulerTest extends TestCase
{
    private ReminderConfig $config;

    protected function setUp(): void
    {
        $this->config = new ReminderConfig();
        $this->config->setDaysBefore(3);
        $this->config->setDaysFirstReminder(1);
        $this->config->setDaysSecondReminder(7);
        $this->config->setDaysFormalNotice(30);
        $this->config->setFormalNoticeEnabled(true);
    }

    /**
     * Verifie qu'un rappel est declenche 3 jours avant l'echeance.
     */
    public function testDetectsBeforeDueReminder(): void
    {
        $today = new \DateTimeImmutable('2026-04-27');
        $dueDate = new \DateTimeImmutable('2026-04-29'); // J-2, dans la fenetre de 3 jours

        $scheduler = $this->createSchedulerForDetermineType();
        $type = $scheduler->determineReminderType($today, $dueDate, $this->config);

        $this->assertSame(ReminderTemplate::TYPE_BEFORE_DUE, $type);
    }

    /**
     * Verifie qu'aucun rappel n'est declenche si l'echeance est loin.
     */
    public function testNoReminderWhenDueDateFarAway(): void
    {
        $today = new \DateTimeImmutable('2026-04-01');
        $dueDate = new \DateTimeImmutable('2026-05-01'); // 30 jours avant

        $scheduler = $this->createSchedulerForDetermineType();
        $type = $scheduler->determineReminderType($today, $dueDate, $this->config);

        $this->assertNull($type);
    }

    /**
     * Verifie qu'une premiere relance est declenchee a J+1.
     */
    public function testDetectsFirstReminder(): void
    {
        $today = new \DateTimeImmutable('2026-05-02');
        $dueDate = new \DateTimeImmutable('2026-05-01'); // J+1

        $scheduler = $this->createSchedulerForDetermineType();
        $type = $scheduler->determineReminderType($today, $dueDate, $this->config);

        $this->assertSame(ReminderTemplate::TYPE_FIRST_REMINDER, $type);
    }

    /**
     * Verifie qu'une deuxieme relance est declenchee a J+7.
     */
    public function testDetectsSecondReminder(): void
    {
        $today = new \DateTimeImmutable('2026-05-08');
        $dueDate = new \DateTimeImmutable('2026-05-01'); // J+7

        $scheduler = $this->createSchedulerForDetermineType();
        $type = $scheduler->determineReminderType($today, $dueDate, $this->config);

        $this->assertSame(ReminderTemplate::TYPE_SECOND_REMINDER, $type);
    }

    /**
     * Verifie qu'une mise en demeure est declenchee a J+30.
     */
    public function testDetectsFormalNotice(): void
    {
        $today = new \DateTimeImmutable('2026-05-31');
        $dueDate = new \DateTimeImmutable('2026-05-01'); // J+30

        $scheduler = $this->createSchedulerForDetermineType();
        $type = $scheduler->determineReminderType($today, $dueDate, $this->config);

        $this->assertSame(ReminderTemplate::TYPE_FORMAL_NOTICE, $type);
    }

    /**
     * Verifie que la mise en demeure est ignoree si desactivee dans la config.
     */
    public function testFormalNoticeDisabled(): void
    {
        $this->config->setFormalNoticeEnabled(false);

        $today = new \DateTimeImmutable('2026-05-31');
        $dueDate = new \DateTimeImmutable('2026-05-01'); // J+30

        $scheduler = $this->createSchedulerForDetermineType();
        $type = $scheduler->determineReminderType($today, $dueDate, $this->config);

        // Doit retomber sur la deuxieme relance
        $this->assertSame(ReminderTemplate::TYPE_SECOND_REMINDER, $type);
    }

    /**
     * Verifie qu'aucune relance le jour exact de l'echeance.
     */
    public function testNoReminderOnDueDate(): void
    {
        $today = new \DateTimeImmutable('2026-05-01');
        $dueDate = new \DateTimeImmutable('2026-05-01'); // J+0 = jour de l'echeance

        $scheduler = $this->createSchedulerForDetermineType();
        $type = $scheduler->determineReminderType($today, $dueDate, $this->config);

        // J+0 : pas de relance (ni avant ni apres)
        $this->assertNull($type);
    }

    /**
     * Verifie les delais personnalises.
     */
    public function testCustomDelays(): void
    {
        $this->config->setDaysBefore(5);
        $this->config->setDaysFirstReminder(3);
        $this->config->setDaysSecondReminder(14);

        // J-4 : dans la fenetre de 5 jours
        $today = new \DateTimeImmutable('2026-04-27');
        $dueDate = new \DateTimeImmutable('2026-05-01');

        $scheduler = $this->createSchedulerForDetermineType();
        $type = $scheduler->determineReminderType($today, $dueDate, $this->config);
        $this->assertSame(ReminderTemplate::TYPE_BEFORE_DUE, $type);

        // J+2 : pas encore la premiere relance (configuree a J+3)
        $today = new \DateTimeImmutable('2026-05-03');
        $type = $scheduler->determineReminderType($today, $dueDate, $this->config);
        $this->assertNull($type);

        // J+3 : premiere relance
        $today = new \DateTimeImmutable('2026-05-04');
        $type = $scheduler->determineReminderType($today, $dueDate, $this->config);
        $this->assertSame(ReminderTemplate::TYPE_FIRST_REMINDER, $type);
    }

    /**
     * Cree un ReminderScheduler avec des mocks pour tester uniquement determineReminderType.
     */
    private function createSchedulerForDetermineType(): ReminderScheduler
    {
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $messageBus = $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        return new ReminderScheduler($em, $messageBus, $logger);
    }
}
