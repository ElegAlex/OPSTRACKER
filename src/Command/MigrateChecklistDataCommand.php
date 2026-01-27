<?php

namespace App\Command;

use App\Entity\Campagne;
use App\Entity\ChecklistInstance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande pour migrer les donnees de checklist vers la nouvelle architecture retroactive.
 *
 * Effectue:
 * 1. Copie les etapes du template vers Campagne.checklistStructure
 * 2. Convertit ChecklistInstance.progression vers etapesCochees
 */
#[AsCommand(
    name: 'app:migrate-checklist-data',
    description: 'Migre les donnees de checklist vers l\'architecture retroactive',
)]
class MigrateChecklistDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les changements sans les appliquer')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force la migration meme si deja effectuee');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('Migration des donnees Checklist vers architecture retroactive');

        if ($dryRun) {
            $io->note('Mode dry-run active - aucune modification ne sera effectuee');
        }

        // Etape 1 : Copier les templates vers les campagnes
        $io->section('Etape 1 : Copie des templates vers les campagnes');
        $campagnesMigrees = $this->migrerCampagnes($io, $dryRun, $force);

        // Etape 2 : Convertir les progressions des instances
        $io->section('Etape 2 : Conversion des progressions des instances');
        $instancesMigrees = $this->migrerInstances($io, $dryRun, $force);

        // Resume
        $io->section('Resume');
        $io->table(
            ['Action', 'Nombre'],
            [
                ['Campagnes migrees', $campagnesMigrees],
                ['Instances migrees', $instancesMigrees],
            ]
        );

        if (!$dryRun && ($campagnesMigrees > 0 || $instancesMigrees > 0)) {
            $this->entityManager->flush();
            $io->success('Migration terminee avec succes.');
        } elseif ($dryRun) {
            $io->info('Dry-run termine. Relancez sans --dry-run pour appliquer les changements.');
        } else {
            $io->info('Aucune migration necessaire.');
        }

        return Command::SUCCESS;
    }

    private function migrerCampagnes(SymfonyStyle $io, bool $dryRun, bool $force): int
    {
        $campagneRepository = $this->entityManager->getRepository(Campagne::class);

        // Trouver les campagnes avec un template mais sans structure
        $qb = $campagneRepository->createQueryBuilder('c')
            ->where('c.checklistTemplate IS NOT NULL');

        if (!$force) {
            $qb->andWhere('c.checklistStructure IS NULL');
        }

        $campagnes = $qb->getQuery()->getResult();

        $count = 0;
        foreach ($campagnes as $campagne) {
            $template = $campagne->getChecklistTemplate();
            if (!$template) {
                continue;
            }

            $io->text(sprintf(
                '  [%d] %s <- Template "%s" (v%d)',
                $campagne->getId(),
                $campagne->getNom(),
                $template->getNom(),
                $template->getVersion()
            ));

            if (!$dryRun) {
                $structure = $template->getEtapes();

                // Ajouter le champ 'actif' a chaque etape
                foreach ($structure['phases'] as &$phase) {
                    foreach ($phase['etapes'] as &$etape) {
                        $etape['actif'] = true;
                        $etape['createdAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
                        $etape['disabledAt'] = null;
                    }
                }

                // Tracer l'origine
                $structure['sourceTemplateId'] = $template->getId();
                $structure['sourceTemplateVersion'] = $template->getVersion();

                $campagne->setChecklistStructure($structure);
            }

            ++$count;
        }

        return $count;
    }

    private function migrerInstances(SymfonyStyle $io, bool $dryRun, bool $force): int
    {
        $instanceRepository = $this->entityManager->getRepository(ChecklistInstance::class);

        // Trouver les instances avec progression mais sans etapesCochees
        $instances = $instanceRepository->findAll();

        $count = 0;
        foreach ($instances as $instance) {
            $progression = $instance->getProgression();
            $etapesCochees = $instance->getEtapesCochees();

            // Sauter si deja migre (sauf si force)
            if (!$force && !empty($etapesCochees)) {
                continue;
            }

            // Sauter si pas de progression a migrer
            if (empty($progression)) {
                continue;
            }

            // Convertir progression vers etapesCochees
            $newEtapesCochees = [];
            foreach ($progression as $etapeId => $data) {
                if ($data['cochee'] ?? false) {
                    $newEtapesCochees[$etapeId] = [
                        'dateCoche' => $data['dateCoche'] ?? (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                        'utilisateurId' => $data['utilisateurId'] ?? 1,
                    ];
                }
            }

            if (empty($newEtapesCochees)) {
                continue;
            }

            $operation = $instance->getOperation();
            $io->text(sprintf(
                '  [%d] Instance pour operation #%d - %d etapes cochees',
                $instance->getId(),
                $operation?->getId() ?? 0,
                count($newEtapesCochees)
            ));

            if (!$dryRun) {
                $instance->setEtapesCochees($newEtapesCochees);
            }

            ++$count;
        }

        return $count;
    }
}
