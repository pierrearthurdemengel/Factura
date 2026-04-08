<?php

namespace App\Command;

use App\Service\Reminder\ReminderScheduler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande executee quotidiennement pour planifier les relances automatiques.
 *
 * Usage : php bin/console app:send-reminders
 * En production : cron quotidien via Fly.io (schedule.
 */
#[AsCommand(
    name: 'app:send-reminders',
    description: 'Detecte les factures a relancer et dispatche les emails de relance',
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly ReminderScheduler $scheduler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable();

        $io->info('Lancement du planificateur de relances pour le ' . $today->format('d/m/Y'));

        $dispatched = $this->scheduler->schedule($today);

        if ($dispatched > 0) {
            $io->success(sprintf('%d relance(s) planifiee(s).', $dispatched));
        } else {
            $io->info('Aucune relance a envoyer aujourd\'hui.');
        }

        return Command::SUCCESS;
    }
}
