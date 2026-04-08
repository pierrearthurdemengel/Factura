<?php

namespace App\Command;

use App\Entity\Company;
use App\Service\Autopilot\AutopilotEngine;
use App\Service\Autopilot\AutopilotRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande executee periodiquement pour evaluer les regles d'autopilot.
 *
 * Parcourt toutes les entreprises actives, evalue les regles configurees
 * et execute les actions correspondantes (relances, alertes, rapports).
 *
 * Usage : php bin/console app:autopilot
 * En production : cron toutes les heures via Fly.io.
 */
#[AsCommand(
    name: 'app:autopilot',
    description: 'Evalue les regles d\'automatisation et execute les actions declenchees',
)]
class RunAutopilotCommand extends Command
{
    public function __construct(
        private readonly AutopilotEngine $engine,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les actions sans les executer')
            ->addOption('company', null, InputOption::VALUE_REQUIRED, 'SIREN d\'une entreprise specifique');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $companySiren = $input->getOption('company');

        $io->info('Demarrage de l\'evaluation autopilot' . ($dryRun ? ' (dry-run)' : ''));

        $companies = $this->getCompanies(is_string($companySiren) ? $companySiren : null);

        if ([] === $companies) {
            $io->warning('Aucune entreprise trouvee.');

            return Command::SUCCESS;
        }

        $totalActions = 0;
        $rules = AutopilotRule::getDefaultRules();

        foreach ($companies as $company) {
            $companyName = $company->getName();
            $actions = $this->engine->evaluate($company, $rules);

            if ([] === $actions) {
                continue;
            }

            $io->section($companyName);

            foreach ($actions as $action) {
                $io->writeln(sprintf(
                    '  [%s] %s → %s',
                    $action['ruleId'],
                    $action['reason'],
                    $action['action'],
                ));
                ++$totalActions;
            }
        }

        if ($totalActions > 0) {
            $label = $dryRun ? 'action(s) a executer (dry-run)' : 'action(s) executee(s)';
            $io->success(sprintf(
                '%d %s pour %d entreprise(s).',
                $totalActions,
                $label,
                count($companies),
            ));
        } else {
            $io->info('Aucune action a executer.');
        }

        return Command::SUCCESS;
    }

    /**
     * Recupere les entreprises a evaluer.
     *
     * @return list<Company>
     */
    private function getCompanies(?string $siren): array
    {
        $repo = $this->em->getRepository(Company::class);

        if (null !== $siren && '' !== $siren) {
            $company = $repo->findOneBy(['siren' => $siren]);

            return null !== $company ? [$company] : [];
        }

        /** @var list<Company> $companies */
        $companies = $repo->findAll();

        return $companies;
    }
}
