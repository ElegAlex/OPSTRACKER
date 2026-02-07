<?php

declare(strict_types=1);

namespace App\Tests\Load;

use App\Entity\Campagne;
use App\Entity\ChecklistInstance;
use App\Entity\ChecklistTemplate;
use App\Entity\Operation;
use App\Entity\Segment;
use App\Entity\TypeOperation;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Generateur de fixtures pour les tests de charge V1.
 *
 * Cible : 50 utilisateurs, 10 000 operations
 */
class LoadTestFixtures
{
    private const USERS_COUNT = 50;
    private const OPERATIONS_COUNT = 10000;
    private const CAMPAGNES_COUNT = 10;
    private const SEGMENTS_PER_CAMPAGNE = 5;

    private array $users = [];
    private array $campagnes = [];
    private array $segments = [];
    private array $operations = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Charge les fixtures de test de charge.
     *
     * @return array{users: int, campagnes: int, operations: int, segments: int}
     */
    public function load(): array
    {
        $this->createTypeOperation();
        $this->createChecklistTemplate();
        $this->createUsers();
        $this->createCampagnes();
        $this->createSegments();
        $this->createOperations();

        $this->em->flush();

        return [
            'users' => count($this->users),
            'campagnes' => count($this->campagnes),
            'operations' => count($this->operations),
            'segments' => count($this->segments),
        ];
    }

    private function createTypeOperation(): TypeOperation
    {
        $type = new TypeOperation();
        $type->setNom('Migration LoadTest');
        $type->setIcone('refresh-cw');
        $type->setCouleur('primary');
        $type->setActif(true);

        $this->em->persist($type);

        return $type;
    }

    private function createChecklistTemplate(): ChecklistTemplate
    {
        $template = new ChecklistTemplate();
        $template->setNom('Checklist LoadTest');
        $template->setVersion(1);
        $template->setActif(true);
        $template->setEtapes([
            'phases' => [
                [
                    'id' => 'phase-1',
                    'nom' => 'Preparation',
                    'ordre' => 1,
                    'etapes' => [
                        ['id' => 'etape-1', 'titre' => 'Sauvegarde donnees', 'obligatoire' => true, 'ordre' => 1],
                        ['id' => 'etape-2', 'titre' => 'Verification materiel', 'obligatoire' => true, 'ordre' => 2],
                    ],
                ],
                [
                    'id' => 'phase-2',
                    'nom' => 'Execution',
                    'ordre' => 2,
                    'etapes' => [
                        ['id' => 'etape-3', 'titre' => 'Installation OS', 'obligatoire' => true, 'ordre' => 1],
                        ['id' => 'etape-4', 'titre' => 'Configuration reseau', 'obligatoire' => true, 'ordre' => 2],
                        ['id' => 'etape-5', 'titre' => 'Installation applications', 'obligatoire' => true, 'ordre' => 3],
                    ],
                ],
                [
                    'id' => 'phase-3',
                    'nom' => 'Validation',
                    'ordre' => 3,
                    'etapes' => [
                        ['id' => 'etape-6', 'titre' => 'Tests fonctionnels', 'obligatoire' => true, 'ordre' => 1],
                        ['id' => 'etape-7', 'titre' => 'Validation utilisateur', 'obligatoire' => false, 'ordre' => 2],
                    ],
                ],
            ],
        ]);

        $this->em->persist($template);

        return $template;
    }

    private function createUsers(): void
    {
        // Admin
        $admin = new Utilisateur();
        $admin->setEmail('admin.loadtest@demo.opstracker.local');
        $admin->setPrenom('Admin');
        $admin->setNom('LoadTest');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'LoadTest2026!'));
        $admin->setActif(true);
        $this->em->persist($admin);
        $this->users[] = $admin;

        // Gestionnaires (Sophie)
        for ($i = 1; $i <= 5; $i++) {
            $user = new Utilisateur();
            $user->setEmail("gestionnaire{$i}.loadtest@demo.opstracker.local");
            $user->setPrenom("Sophie{$i}");
            $user->setNom('LoadTest');
            $user->setRoles(['ROLE_GESTIONNAIRE']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'LoadTest2026!'));
            $user->setActif(true);
            $this->em->persist($user);
            $this->users[] = $user;
        }

        // Techniciens (Karim)
        for ($i = 1; $i <= self::USERS_COUNT - 6; $i++) {
            $user = new Utilisateur();
            $user->setEmail("technicien{$i}.loadtest@demo.opstracker.local");
            $user->setPrenom("Karim{$i}");
            $user->setNom('LoadTest');
            $user->setRoles(['ROLE_TECHNICIEN']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'LoadTest2026!'));
            $user->setActif(true);
            $this->em->persist($user);
            $this->users[] = $user;
        }
    }

    private function createCampagnes(): void
    {
        $statuts = [
            Campagne::STATUT_EN_COURS,
            Campagne::STATUT_EN_COURS,
            Campagne::STATUT_EN_COURS,
            Campagne::STATUT_EN_COURS,
            Campagne::STATUT_EN_COURS,
            Campagne::STATUT_A_VENIR,
            Campagne::STATUT_A_VENIR,
            Campagne::STATUT_TERMINE,
            Campagne::STATUT_TERMINE,
            Campagne::STATUT_BROUILLON,
        ];

        for ($i = 1; $i <= self::CAMPAGNES_COUNT; $i++) {
            $campagne = new Campagne();
            $campagne->setNom("Campagne LoadTest {$i}");
            $campagne->setDescription("Campagne de test de charge numero {$i}");
            $campagne->setDateDebut(new \DateTimeImmutable("-{$i} months"));
            $campagne->setDateFin(new \DateTimeImmutable("+{$i} months"));

            // Forcer le statut via reflection
            $reflection = new \ReflectionClass($campagne);
            $property = $reflection->getProperty('statut');
            $property->setValue($campagne, $statuts[$i - 1]);

            $this->em->persist($campagne);
            $this->campagnes[] = $campagne;
        }
    }

    private function createSegments(): void
    {
        $colors = ['primary', 'success', 'warning', 'danger', 'muted'];
        $buildings = ['Batiment A', 'Batiment B', 'Batiment C', 'Batiment D', 'Batiment E'];

        foreach ($this->campagnes as $campagne) {
            for ($i = 0; $i < self::SEGMENTS_PER_CAMPAGNE; $i++) {
                $segment = new Segment();
                $segment->setNom($buildings[$i] . ' - ' . $campagne->getNom());
                $segment->setCouleur($colors[$i]);
                $segment->setCampagne($campagne);
                $segment->setOrdre($i + 1);

                $this->em->persist($segment);
                $this->segments[] = $segment;
            }
        }
    }

    private function createOperations(): void
    {
        $statuts = [
            Operation::STATUT_A_PLANIFIER,
            Operation::STATUT_PLANIFIE,
            Operation::STATUT_EN_COURS,
            Operation::STATUT_REALISE,
            Operation::STATUT_REALISE,
            Operation::STATUT_REALISE,
            Operation::STATUT_REPORTE,
            Operation::STATUT_A_REMEDIER,
        ];

        $techniciens = array_filter($this->users, fn($u) => in_array('ROLE_TECHNICIEN', $u->getRoles()));
        $techniciens = array_values($techniciens);
        $techCount = count($techniciens);

        $operationsPerCampagne = (int) ceil(self::OPERATIONS_COUNT / self::CAMPAGNES_COUNT);

        foreach ($this->campagnes as $campagneIndex => $campagne) {
            $campagneSegments = array_filter(
                $this->segments,
                fn($s) => $s->getCampagne() === $campagne
            );
            $campagneSegments = array_values($campagneSegments);
            $segmentCount = count($campagneSegments);

            for ($i = 0; $i < $operationsPerCampagne; $i++) {
                if (count($this->operations) >= self::OPERATIONS_COUNT) {
                    break;
                }

                $operation = new Operation();
                $operation->setMatricule(sprintf('LT-%05d', count($this->operations) + 1));
                $operation->setNom(sprintf('Poste LoadTest %d', count($this->operations) + 1));
                $operation->setCampagne($campagne);

                if ($segmentCount > 0) {
                    $operation->setSegment($campagneSegments[$i % $segmentCount]);
                }

                // Assigner un technicien aleatoire
                if ($techCount > 0) {
                    $operation->setTechnicienAssigne($techniciens[$i % $techCount]);
                }

                // Statut aleatoire
                $statut = $statuts[array_rand($statuts)];
                $operation->setStatut($statut);

                // Dates selon le statut
                if (in_array($statut, [Operation::STATUT_PLANIFIE, Operation::STATUT_EN_COURS, Operation::STATUT_REALISE])) {
                    $operation->setDatePlanifiee(new \DateTimeImmutable('+' . rand(1, 30) . ' days'));
                }

                if ($statut === Operation::STATUT_REALISE) {
                    $operation->setDateRealisation(new \DateTimeImmutable('-' . rand(1, 30) . ' days'));
                }

                if ($statut === Operation::STATUT_REPORTE) {
                    $operation->setMotifReport('Indisponibilite utilisateur');
                }

                $this->em->persist($operation);
                $this->operations[] = $operation;

                // Flush periodique pour eviter problemes memoire
                if (count($this->operations) % 1000 === 0) {
                    $this->em->flush();
                    $this->em->clear(Operation::class);
                }
            }
        }
    }

    /**
     * Supprime toutes les donnees de test de charge.
     */
    public function purge(): void
    {
        $conn = $this->em->getConnection();

        // Desactiver les contraintes FK temporairement
        $conn->executeStatement('SET session_replication_role = replica');

        // Supprimer les donnees de test
        $conn->executeStatement("DELETE FROM operation WHERE matricule LIKE 'LT-%'");
        $conn->executeStatement("DELETE FROM segment WHERE nom LIKE '%LoadTest%'");
        $conn->executeStatement("DELETE FROM campagne WHERE nom LIKE '%LoadTest%'");
        $conn->executeStatement("DELETE FROM utilisateur WHERE email LIKE '%loadtest@demo.opstracker.local'");
        $conn->executeStatement("DELETE FROM type_operation WHERE nom LIKE '%LoadTest%'");
        $conn->executeStatement("DELETE FROM checklist_template WHERE nom LIKE '%LoadTest%'");

        // Reactiver les contraintes FK
        $conn->executeStatement('SET session_replication_role = DEFAULT');
    }
}
