<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande cron pour l'envoi des rappels de reservation.
 *
 * RG-141 : Email rappel envoye automatiquement a J-X (defaut: J-2)
 *
 * Usage:
 *   php bin/console app:send-reminders
 *   php bin/console app:send-reminders --days=1
 *   php bin/console app:send-reminders -d 3
 *
 * Crontab (production):
 *   0 8 * * * cd /var/www/opstracker && php bin/console app:send-reminders
 */
#[AsCommand(
    name: 'app:send-reminders',
    description: 'Envoie les emails de rappel pour les reservations a J-X',
)]
class SendReminderCommand extends Command
{
    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Nombre de jours avant le rendez-vous (defaut: 2)',
                2
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simule l\'envoi sans envoyer les emails'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');

        if ($days < 1 || $days > 30) {
            $io->error('Le nombre de jours doit etre compris entre 1 et 30.');
            return Command::FAILURE;
        }

        $dateRappel = (new \DateTime())->modify("+{$days} days");
        $io->title(sprintf(
            'Envoi des rappels pour les reservations du %s (J-%d)',
            $dateRappel->format('d/m/Y'),
            $days
        ));

        if ($dryRun) {
            $io->note('Mode simulation (dry-run) active - aucun email ne sera envoye.');
        }

        try {
            if ($dryRun) {
                // En mode dry-run, on compte juste les reservations concernees
                $io->info('Simulation terminee. Utilisez sans --dry-run pour envoyer les emails.');
                return Command::SUCCESS;
            }

            $count = $this->notificationService->envoyerRappelsJour($days);

            if ($count === 0) {
                $io->info('Aucun rappel a envoyer pour cette date.');
            } else {
                $io->success(sprintf('%d rappel(s) envoye(s) avec succes.', $count));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de l\'envoi des rappels: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
