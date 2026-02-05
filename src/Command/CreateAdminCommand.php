<?php

namespace App\Command;

use App\Service\UtilisateurService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Cree un compte administrateur',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private UtilisateurService $utilisateurService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Adresse email')
            ->addArgument('nom', InputArgument::OPTIONAL, 'Nom de famille')
            ->addArgument('prenom', InputArgument::OPTIONAL, 'Prenom')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Mot de passe (non-interactif)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $io->title('Creation d\'un compte administrateur');

        // Email
        $email = $input->getArgument('email');
        if (!$email) {
            $question = new Question('Adresse email: ');
            $email = $helper->ask($input, $output, $question);
        }

        // Nom
        $nom = $input->getArgument('nom');
        if (!$nom) {
            $question = new Question('Nom de famille: ');
            $nom = $helper->ask($input, $output, $question);
        }

        // Prénom
        $prenom = $input->getArgument('prenom');
        if (!$prenom) {
            $question = new Question('Prenom: ');
            $prenom = $helper->ask($input, $output, $question);
        }

        // Mot de passe (option ou interactif)
        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Mot de passe (RG-001: 8 car min, 1 maj, 1 chiffre, 1 special): ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);

            // Confirmation seulement en mode interactif
            $question = new Question('Confirmer le mot de passe: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $passwordConfirm = $helper->ask($input, $output, $question);

            if ($password !== $passwordConfirm) {
                $io->error('Les mots de passe ne correspondent pas.');
                return Command::FAILURE;
            }
        }

        try {
            $utilisateur = $this->utilisateurService->createAdmin(
                $email,
                $password,
                $nom,
                $prenom,
            );

            $io->success(sprintf(
                'Administrateur cree: %s <%s>',
                $utilisateur->getNomComplet(),
                $utilisateur->getEmail(),
            ));

            return Command::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
