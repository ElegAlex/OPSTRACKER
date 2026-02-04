<?php

namespace App\Command;

use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use App\Service\SmsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande cron pour l'envoi des rappels de reservation.
 *
 * Sprint V2.1c : Support des rappels email (J-2) ET SMS (J-1)
 *
 * RG-141 : Email rappel envoye automatiquement a J-X (defaut: J-2)
 * Nouveau : SMS rappel envoye automatiquement a J-1
 *
 * Usage:
 *   php bin/console app:send-reminders                    # Email J-2 + SMS J-1
 *   php bin/console app:send-reminders --type=email       # Email J-2 uniquement
 *   php bin/console app:send-reminders --type=sms         # SMS J-1 uniquement
 *   php bin/console app:send-reminders --email-days=1     # Email J-1 (ajuste)
 *   php bin/console app:send-reminders --sms-days=2       # SMS J-2 (ajuste)
 *
 * Crontab (production):
 *   0 8 * * *  cd /var/www/opstracker && php bin/console app:send-reminders --type=email
 *   0 18 * * * cd /var/www/opstracker && php bin/console app:send-reminders --type=sms
 */
#[AsCommand(
    name: 'app:send-reminders',
    description: 'Envoie les rappels email (J-2) et SMS (J-1) pour les reservations',
)]
class SendReminderCommand extends Command
{
    public function __construct(
        private NotificationService $notificationService,
        private SmsService $smsService,
        private ReservationRepository $reservationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'type',
                't',
                InputOption::VALUE_OPTIONAL,
                'Type de rappel : all, email, sms (defaut: all)',
                'all'
            )
            ->addOption(
                'email-days',
                null,
                InputOption::VALUE_OPTIONAL,
                'Jours avant le RDV pour les emails (defaut: 2)',
                2
            )
            ->addOption(
                'sms-days',
                null,
                InputOption::VALUE_OPTIONAL,
                'Jours avant le RDV pour les SMS (defaut: 1)',
                1
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simule l\'envoi sans envoyer les notifications'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getOption('type');
        $dryRun = $input->getOption('dry-run');

        if (!in_array($type, ['all', 'email', 'sms'], true)) {
            $io->error("Type invalide : {$type}. Valeurs acceptees : all, email, sms");
            return Command::FAILURE;
        }

        $io->title('Envoi des rappels de reservation');

        if ($dryRun) {
            $io->note('Mode simulation (dry-run) - aucune notification ne sera envoyee.');
        }

        $emailCount = 0;
        $smsCount = 0;

        // Emails J-X
        if ($type === 'all' || $type === 'email') {
            $emailDays = (int) $input->getOption('email-days');
            if ($emailDays < 1 || $emailDays > 30) {
                $io->error('email-days doit etre entre 1 et 30.');
                return Command::FAILURE;
            }

            $emailCount = $this->envoyerRappelsEmail($io, $emailDays, $dryRun);
        }

        // SMS J-X
        if ($type === 'all' || $type === 'sms') {
            $smsDays = (int) $input->getOption('sms-days');
            if ($smsDays < 1 || $smsDays > 30) {
                $io->error('sms-days doit etre entre 1 et 30.');
                return Command::FAILURE;
            }

            $smsCount = $this->envoyerRappelsSms($io, $smsDays, $dryRun);
        }

        // Resume
        $io->newLine();
        $io->section('Resume');

        if ($type === 'all' || $type === 'email') {
            $io->text(sprintf('Emails envoyes : %d', $emailCount));
        }
        if ($type === 'all' || $type === 'sms') {
            $io->text(sprintf('SMS envoyes : %d', $smsCount));
        }

        if (!$dryRun && ($emailCount > 0 || $smsCount > 0)) {
            $io->success('Rappels envoyes avec succes.');
        } elseif ($dryRun) {
            $io->info('Simulation terminee. Relancez sans --dry-run pour envoyer.');
        } else {
            $io->info('Aucun rappel a envoyer.');
        }

        return Command::SUCCESS;
    }

    /**
     * Envoie les rappels email pour les reservations a J+X.
     */
    private function envoyerRappelsEmail(SymfonyStyle $io, int $joursAvant, bool $dryRun): int
    {
        $dateRappel = (new \DateTime())->modify("+{$joursAvant} days");

        $io->section(sprintf(
            'Rappels Email - J-%d (%s)',
            $joursAvant,
            $dateRappel->format('d/m/Y')
        ));

        if ($dryRun) {
            $reservations = $this->reservationRepository->findPourRappel($dateRappel);
            $io->text(sprintf('%d reservation(s) concernee(s)', count($reservations)));
            return 0;
        }

        try {
            $count = $this->notificationService->envoyerRappelsJour($joursAvant);
            $io->text(sprintf('%d email(s) de rappel envoye(s)', $count));
            return $count;
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur envoi emails : %s', $e->getMessage()));
            return 0;
        }
    }

    /**
     * Envoie les rappels SMS pour les reservations a J+X.
     */
    private function envoyerRappelsSms(SymfonyStyle $io, int $joursAvant, bool $dryRun): int
    {
        $dateRappel = (new \DateTime())->modify("+{$joursAvant} days");

        $io->section(sprintf(
            'Rappels SMS - J-%d (%s)',
            $joursAvant,
            $dateRappel->format('d/m/Y')
        ));

        // Verifier si SMS est active
        if (!$this->smsService->isEnabled()) {
            $io->warning('SMS desactive (SMS_ENABLED=false)');
            return 0;
        }

        $io->text(sprintf('Provider SMS : %s', $this->smsService->getProviderName()));

        // Recuperer les reservations pour cette date
        $reservations = $this->reservationRepository->findPourRappel($dateRappel);
        $io->text(sprintf('%d reservation(s) pour cette date', count($reservations)));

        if ($dryRun) {
            // En mode dry-run, compter celles eligibles
            $eligibles = 0;
            foreach ($reservations as $reservation) {
                $agent = $reservation->getAgent();
                if ($agent !== null && $agent->canReceiveSms()) {
                    $eligibles++;
                }
            }
            $io->text(sprintf('%d agent(s) eligible(s) au SMS (opt-in + telephone)', $eligibles));
            return 0;
        }

        // Envoyer les SMS
        $count = 0;
        foreach ($reservations as $reservation) {
            if ($reservation->getStatut() !== 'confirmee') {
                continue;
            }

            try {
                if ($this->smsService->envoyerRappel($reservation)) {
                    $count++;
                }
            } catch (\Exception $e) {
                $agent = $reservation->getAgent();
                $io->warning(sprintf(
                    'Erreur SMS pour agent %s : %s',
                    $agent?->getMatricule() ?? 'inconnu',
                    $e->getMessage()
                ));
            }
        }

        $io->text(sprintf('%d SMS de rappel envoye(s)', $count));

        return $count;
    }
}
