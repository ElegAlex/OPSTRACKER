<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use App\Repository\OperationRepository;
use App\Repository\CampagneRepository;
use App\Service\DashboardService;
use App\Tests\Load\LoadTestFixtures;

/**
 * Commande de test de charge V1.
 *
 * Usage:
 *   php bin/console app:load-test --setup     # Creer les fixtures
 *   php bin/console app:load-test --run       # Lancer les benchmarks
 *   php bin/console app:load-test --cleanup   # Supprimer les fixtures
 *   php bin/console app:load-test --full      # Setup + Run + Cleanup
 */
#[AsCommand(
    name: 'app:load-test',
    description: 'Execute les tests de charge V1 (50 users, 10k operations)'
)]
class LoadTestCommand extends Command
{
    private const TARGET_USERS = 50;
    private const TARGET_OPERATIONS = 10000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly OperationRepository $operationRepository,
        private readonly CampagneRepository $campagneRepository,
        private readonly DashboardService $dashboardService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('setup', null, InputOption::VALUE_NONE, 'Creer les fixtures de test')
            ->addOption('run', null, InputOption::VALUE_NONE, 'Executer les benchmarks')
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Supprimer les fixtures')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Setup + Run + Cleanup');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('OpsTracker - Test de Charge V1');
        $io->text(sprintf('Cible: %d utilisateurs, %d operations', self::TARGET_USERS, self::TARGET_OPERATIONS));
        $io->newLine();

        $fixtures = new LoadTestFixtures($this->em, $this->passwordHasher);

        if ($input->getOption('full')) {
            $this->runSetup($io, $fixtures);
            $results = $this->runBenchmarks($io);
            $this->runCleanup($io, $fixtures);
            $this->generateReport($io, $results);
            return Command::SUCCESS;
        }

        if ($input->getOption('setup')) {
            $this->runSetup($io, $fixtures);
            return Command::SUCCESS;
        }

        if ($input->getOption('run')) {
            $results = $this->runBenchmarks($io);
            $this->generateReport($io, $results);
            return Command::SUCCESS;
        }

        if ($input->getOption('cleanup')) {
            $this->runCleanup($io, $fixtures);
            return Command::SUCCESS;
        }

        $io->error('Specifiez une option: --setup, --run, --cleanup ou --full');
        return Command::FAILURE;
    }

    private function runSetup(SymfonyStyle $io, LoadTestFixtures $fixtures): void
    {
        $io->section('Setup - Creation des fixtures');

        $stopwatch = new Stopwatch();
        $stopwatch->start('setup');

        $counts = $fixtures->load();

        $event = $stopwatch->stop('setup');

        $io->success(sprintf(
            'Fixtures creees en %.2f secondes',
            $event->getDuration() / 1000
        ));

        $io->table(
            ['Element', 'Nombre'],
            [
                ['Utilisateurs', $counts['users']],
                ['Campagnes', $counts['campagnes']],
                ['Segments', $counts['segments']],
                ['Operations', $counts['operations']],
            ]
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function runBenchmarks(SymfonyStyle $io): array
    {
        $io->section('Benchmarks - Mesure des performances');

        $results = [];
        $stopwatch = new Stopwatch();

        // Benchmark 1: Liste des operations (pagination)
        $io->text('Benchmark 1: Liste operations paginee...');
        $stopwatch->start('operations_list');
        for ($i = 0; $i < 100; $i++) {
            $this->operationRepository->findBy([], ['id' => 'DESC'], 50, $i * 50);
        }
        $event = $stopwatch->stop('operations_list');
        $results['operations_list'] = [
            'name' => 'Liste operations (100 pages x 50)',
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
            'iterations' => 100,
        ];

        // Benchmark 2: Dashboard KPI
        $io->text('Benchmark 2: Dashboard KPI campagne...');
        $campagnes = $this->campagneRepository->findBy(['statut' => 'en_cours']);
        $stopwatch->start('dashboard_kpi');
        foreach ($campagnes as $campagne) {
            for ($i = 0; $i < 10; $i++) {
                $this->dashboardService->getKpiCampagne($campagne);
            }
        }
        $event = $stopwatch->stop('dashboard_kpi');
        $results['dashboard_kpi'] = [
            'name' => 'Dashboard KPI (campagnes x 10)',
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
            'iterations' => count($campagnes) * 10,
        ];

        // Benchmark 3: Recherche operations par statut
        $io->text('Benchmark 3: Recherche par statut...');
        $stopwatch->start('search_status');
        foreach (['a_planifier', 'planifie', 'en_cours', 'realise', 'reporte', 'a_remedier'] as $statut) {
            for ($i = 0; $i < 20; $i++) {
                $this->operationRepository->findBy(['statut' => $statut], null, 100);
            }
        }
        $event = $stopwatch->stop('search_status');
        $results['search_status'] = [
            'name' => 'Recherche par statut (6 statuts x 20)',
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
            'iterations' => 120,
        ];

        // Benchmark 4: Progression segments
        $io->text('Benchmark 4: Progression segments...');
        $stopwatch->start('segments_progress');
        foreach ($campagnes as $campagne) {
            for ($i = 0; $i < 10; $i++) {
                $this->dashboardService->getProgressionParSegment($campagne);
            }
        }
        $event = $stopwatch->stop('segments_progress');
        $results['segments_progress'] = [
            'name' => 'Progression segments (campagnes x 10)',
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
            'iterations' => count($campagnes) * 10,
        ];

        // Benchmark 5: Dashboard global
        $io->text('Benchmark 5: Dashboard global...');
        $stopwatch->start('dashboard_global');
        for ($i = 0; $i < 50; $i++) {
            $this->dashboardService->getDashboardGlobal();
        }
        $event = $stopwatch->stop('dashboard_global');
        $results['dashboard_global'] = [
            'name' => 'Dashboard global (50 iterations)',
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
            'iterations' => 50,
        ];

        // Benchmark 6: Comptage par campagne
        $io->text('Benchmark 6: Comptage operations par campagne...');
        $stopwatch->start('count_by_campagne');
        foreach ($campagnes as $campagne) {
            for ($i = 0; $i < 20; $i++) {
                $this->operationRepository->count(['campagne' => $campagne]);
            }
        }
        $event = $stopwatch->stop('count_by_campagne');
        $results['count_by_campagne'] = [
            'name' => 'Comptage par campagne (campagnes x 20)',
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
            'iterations' => count($campagnes) * 20,
        ];

        return $results;
    }

    private function runCleanup(SymfonyStyle $io, LoadTestFixtures $fixtures): void
    {
        $io->section('Cleanup - Suppression des fixtures');

        $stopwatch = new Stopwatch();
        $stopwatch->start('cleanup');

        $fixtures->purge();

        $event = $stopwatch->stop('cleanup');

        $io->success(sprintf(
            'Fixtures supprimees en %.2f secondes',
            $event->getDuration() / 1000
        ));
    }

    /**
     * @param array<string, array<string, mixed>> $results
     */
    private function generateReport(SymfonyStyle $io, array $results): void
    {
        $io->section('Rapport de Performance');

        $rows = [];
        $totalDuration = 0;

        foreach ($results as $key => $result) {
            $avgMs = $result['duration'] / $result['iterations'];
            $totalDuration += $result['duration'];

            $rows[] = [
                $result['name'],
                $result['iterations'],
                sprintf('%.2f ms', $result['duration']),
                sprintf('%.2f ms', $avgMs),
                $this->formatMemory($result['memory']),
                $avgMs < 100 ? 'OK' : ($avgMs < 500 ? 'WARN' : 'SLOW'),
            ];
        }

        $io->table(
            ['Benchmark', 'Iterations', 'Total', 'Moyenne', 'Memoire', 'Statut'],
            $rows
        );

        $io->newLine();
        $io->text(sprintf('Duree totale: %.2f secondes', $totalDuration / 1000));

        // Verdict
        $slowCount = count(array_filter($results, fn($r) => ($r['duration'] / $r['iterations']) >= 500));
        $warnCount = count(array_filter($results, fn($r) => ($r['duration'] / $r['iterations']) >= 100 && ($r['duration'] / $r['iterations']) < 500));

        if ($slowCount === 0 && $warnCount === 0) {
            $io->success('VERDICT: Tous les benchmarks sont dans les limites acceptables');
        } elseif ($slowCount === 0) {
            $io->warning(sprintf('VERDICT: %d benchmark(s) en warning mais acceptable', $warnCount));
        } else {
            $io->error(sprintf('VERDICT: %d benchmark(s) trop lent(s)', $slowCount));
        }

        // Seuils V1
        $io->newLine();
        $io->text('Seuils V1:');
        $io->listing([
            'OK: < 100ms par requete',
            'WARN: 100-500ms par requete',
            'SLOW: > 500ms par requete',
        ]);
    }

    private function formatMemory(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1024 * 1024) {
            return sprintf('%.1f KB', $bytes / 1024);
        } else {
            return sprintf('%.1f MB', $bytes / (1024 * 1024));
        }
    }
}
