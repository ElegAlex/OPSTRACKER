<?php

namespace App\Command;

use App\Entity\Agent;
use App\Repository\AgentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-agents',
    description: 'Importe des agents depuis un fichier CSV'
)]
class ImportAgentsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgentRepository $agentRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin du fichier CSV')
            ->addOption('separator', 's', InputOption::VALUE_OPTIONAL, 'Separateur CSV', ';')
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Mettre a jour les agents existants (par matricule)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        $separator = $input->getOption('separator');
        $update = $input->getOption('update');

        if (!file_exists($file)) {
            $io->error("Fichier non trouve : $file");

            return Command::FAILURE;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            $io->error("Impossible d'ouvrir le fichier : $file");

            return Command::FAILURE;
        }

        // Lire les headers
        $headers = fgetcsv($handle, 0, $separator);
        if (!$headers) {
            $io->error('Fichier CSV vide ou invalide');
            fclose($handle);

            return Command::FAILURE;
        }
        $headers = array_map('strtolower', array_map(fn($v) => trim((string) $v), $headers));

        $io->info('Colonnes detectees : ' . implode(', ', $headers));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $lineNumber = 1;
        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            ++$lineNumber;

            if (count($row) !== count($headers)) {
                $errors[] = "Ligne $lineNumber : nombre de colonnes incorrect";
                continue;
            }

            $data = array_combine($headers, array_map(fn($v) => trim((string) $v), $row));

            // Verifier les champs obligatoires
            $matricule = $data['matricule'] ?? null;
            $email = $data['email'] ?? null;
            $nom = $data['nom'] ?? null;
            $prenom = $data['prenom'] ?? null;

            if (!$matricule || !$email || !$nom || !$prenom) {
                $errors[] = "Ligne $lineNumber : champs obligatoires manquants (matricule, email, nom, prenom)";
                continue;
            }

            // Chercher si l'agent existe deja
            $agent = $this->agentRepository->findOneByMatricule($matricule);

            if ($agent && !$update) {
                ++$skipped;
                continue;
            }

            if (!$agent) {
                $agent = new Agent();
                $agent->setActif(true);
                $agent->generateBookingToken();
                ++$created;
            } else {
                ++$updated;
            }

            try {
                $agent->setMatricule($matricule);
                $agent->setEmail($email);
                $agent->setNom($nom);
                $agent->setPrenom($prenom);
                $agent->setService($data['service'] ?? null);
                $agent->setSite($data['site'] ?? null);
                $agent->setRole($data['role'] ?? null);
                $agent->setTypeContrat($data['typecontrat'] ?? $data['type_contrat'] ?? null);

                // Telephone si present
                if (!empty($data['telephone'])) {
                    try {
                        $agent->setTelephone($data['telephone']);
                    } catch (\InvalidArgumentException $e) {
                        // Ignorer les telephones invalides
                    }
                }

                $this->em->persist($agent);
            } catch (\Exception $e) {
                $errors[] = "Ligne $lineNumber : " . $e->getMessage();
                continue;
            }
        }

        fclose($handle);

        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $io->error('Erreur lors de la sauvegarde : ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Afficher le resultat
        $io->success([
            "Import termine !",
            "Crees : $created",
            "Mis a jour : $updated",
            "Ignores (deja existants) : $skipped",
        ]);

        if (!empty($errors)) {
            $io->warning('Erreurs rencontrees :');
            foreach (array_slice($errors, 0, 10) as $error) {
                $io->text("  - $error");
            }
            if (count($errors) > 10) {
                $io->text('  ... et ' . (count($errors) - 10) . ' autres erreurs');
            }
        }

        return Command::SUCCESS;
    }
}
