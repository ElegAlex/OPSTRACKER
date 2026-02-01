<?php

namespace App\Command;

use App\Repository\CampagneRepository;
use App\Service\SegmentSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-segments',
    description: 'Synchronise les segments depuis la colonne designee (colonneSegment)',
)]
class SyncSegmentsCommand extends Command
{
    public function __construct(
        private CampagneRepository $campagneRepository,
        private SegmentSyncService $segmentSyncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('campagne_id', InputArgument::REQUIRED, 'ID de la campagne')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $campagneId = (int) $input->getArgument('campagne_id');

        $campagne = $this->campagneRepository->find($campagneId);

        if (!$campagne) {
            $io->error(sprintf('Campagne #%d non trouvee.', $campagneId));

            return Command::FAILURE;
        }

        $colonneSegment = $campagne->getColonneSegment();

        if (!$colonneSegment) {
            $io->warning(sprintf('La campagne "%s" n\'a pas de colonneSegment definie.', $campagne->getNom()));

            return Command::FAILURE;
        }

        $io->info(sprintf('Synchronisation des segments pour la campagne "%s" (colonne: %s)...', $campagne->getNom(), $colonneSegment));

        $created = $this->segmentSyncService->syncFromColonne($campagne);

        $io->success(sprintf('%d segment(s) cree(s). Operations assignees aux segments.', $created));

        return Command::SUCCESS;
    }
}
