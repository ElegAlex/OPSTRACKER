<?php

namespace App\DataFixtures;

use App\Entity\Campagne;
use App\Entity\ChecklistInstance;
use App\Entity\ChecklistTemplate;
use App\Entity\Operation;
use App\Entity\Segment;
use App\Entity\TypeOperation;
use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Fixtures de demo OpsTracker - Sprint 8 (T-801)
 *
 * Donnees generees :
 * - 6 utilisateurs (admin, gestionnaire, 4 techniciens)
 * - 3 types d'operation
 * - 2 templates checklist
 * - 3 campagnes (en_cours, a_venir, terminee)
 * - 9 segments (3 par campagne)
 * - 150 operations reparties
 * - Checklist instances pour operations en cours/realisees
 */
class AppFixtures extends Fixture
{
    private Generator $faker;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        $this->faker = Factory::create('fr_FR');
    }

    public function load(ObjectManager $manager): void
    {
        // Seed pour reproductibilite
        $this->faker->seed(2026);

        // 1. Creer les utilisateurs
        $utilisateurs = $this->createUtilisateurs($manager);

        // 2. Creer les types d'operation
        $typesOperation = $this->createTypesOperation($manager);

        // 3. Creer les templates de checklist
        $templates = $this->createChecklistTemplates($manager);

        // 4. Creer les campagnes avec segments et operations
        $this->createCampagnes($manager, $utilisateurs, $typesOperation, $templates);

        $manager->flush();
    }

    /**
     * Cree les utilisateurs de demo
     *
     * @return array<string, Utilisateur>
     */
    private function createUtilisateurs(ObjectManager $manager): array
    {
        $utilisateurs = [];

        // Admin
        $admin = new Utilisateur();
        $admin->setEmail('admin@cpam92.fr');
        $admin->setNom('DURAND');
        $admin->setPrenom('Philippe');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setActif(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin123!'));
        $manager->persist($admin);
        $utilisateurs['admin'] = $admin;

        // Sophie - Gestionnaire IT
        $sophie = new Utilisateur();
        $sophie->setEmail('sophie.martin@cpam92.fr');
        $sophie->setNom('MARTIN');
        $sophie->setPrenom('Sophie');
        $sophie->setRoles(['ROLE_GESTIONNAIRE']);
        $sophie->setActif(true);
        $sophie->setPassword($this->passwordHasher->hashPassword($sophie, 'Sophie123!'));
        $manager->persist($sophie);
        $utilisateurs['sophie'] = $sophie;

        // Karim - Technicien IT principal
        $karim = new Utilisateur();
        $karim->setEmail('karim.benali@cpam92.fr');
        $karim->setNom('BENALI');
        $karim->setPrenom('Karim');
        $karim->setRoles(['ROLE_TECHNICIEN']);
        $karim->setActif(true);
        $karim->setPassword($this->passwordHasher->hashPassword($karim, 'Karim123!'));
        $manager->persist($karim);
        $utilisateurs['karim'] = $karim;

        // Autres techniciens
        $techNames = [
            ['DUPONT', 'Marie', 'marie.dupont@cpam92.fr'],
            ['LECLERC', 'Thomas', 'thomas.leclerc@cpam92.fr'],
            ['MOREAU', 'Julie', 'julie.moreau@cpam92.fr'],
        ];

        foreach ($techNames as $i => $data) {
            $tech = new Utilisateur();
            $tech->setEmail($data[2]);
            $tech->setNom($data[0]);
            $tech->setPrenom($data[1]);
            $tech->setRoles(['ROLE_TECHNICIEN']);
            $tech->setActif(true);
            $tech->setPassword($this->passwordHasher->hashPassword($tech, 'Tech123!'));
            $manager->persist($tech);
            $utilisateurs['tech' . ($i + 1)] = $tech;
        }

        return $utilisateurs;
    }

    /**
     * Cree les types d'operation
     *
     * @return array<string, TypeOperation>
     */
    private function createTypesOperation(ObjectManager $manager): array
    {
        $types = [];

        // Migration PC Windows 11
        $migrationPC = new TypeOperation();
        $migrationPC->setNom('Migration PC Windows 11');
        $migrationPC->setDescription('Migration du parc informatique vers Windows 11 avec transfert des donnees et configuration des applications metier.');
        $migrationPC->setIcone('refresh-cw');
        $migrationPC->setCouleur('primary');
        $migrationPC->setChampsPersonnalises([
            [
                'code' => 'ancien_pc',
                'label' => 'Ancien PC (numero inventaire)',
                'type' => 'text_court',
                'obligatoire' => true,
            ],
            [
                'code' => 'nouveau_pc',
                'label' => 'Nouveau PC (numero inventaire)',
                'type' => 'text_court',
                'obligatoire' => true,
            ],
            [
                'code' => 'applications_metier',
                'label' => 'Applications metier a installer',
                'type' => 'text_long',
                'obligatoire' => false,
            ],
        ]);
        $migrationPC->setActif(true);
        $manager->persist($migrationPC);
        $types['migration_pc'] = $migrationPC;

        // Installation logiciel
        $installLogiciel = new TypeOperation();
        $installLogiciel->setNom('Installation logiciel');
        $installLogiciel->setDescription('Installation et configuration de logiciels metier sur les postes de travail.');
        $installLogiciel->setIcone('download');
        $installLogiciel->setCouleur('success');
        $installLogiciel->setChampsPersonnalises([
            [
                'code' => 'logiciel',
                'label' => 'Nom du logiciel',
                'type' => 'text_court',
                'obligatoire' => true,
            ],
            [
                'code' => 'version',
                'label' => 'Version',
                'type' => 'text_court',
                'obligatoire' => true,
            ],
            [
                'code' => 'licence',
                'label' => 'Cle de licence',
                'type' => 'text_court',
                'obligatoire' => false,
            ],
        ]);
        $installLogiciel->setActif(true);
        $manager->persist($installLogiciel);
        $types['installation'] = $installLogiciel;

        // Maintenance preventive
        $maintenance = new TypeOperation();
        $maintenance->setNom('Maintenance preventive');
        $maintenance->setDescription('Verification et nettoyage du materiel, mise a jour systeme, verification antivirus.');
        $maintenance->setIcone('wrench');
        $maintenance->setCouleur('warning');
        $maintenance->setChampsPersonnalises([
            [
                'code' => 'type_maintenance',
                'label' => 'Type de maintenance',
                'type' => 'liste',
                'obligatoire' => true,
                'options' => ['Nettoyage', 'Mise a jour', 'Verification complete'],
            ],
        ]);
        $maintenance->setActif(true);
        $manager->persist($maintenance);
        $types['maintenance'] = $maintenance;

        return $types;
    }

    /**
     * Cree les templates de checklist
     *
     * @return array<string, ChecklistTemplate>
     */
    private function createChecklistTemplates(ObjectManager $manager): array
    {
        $templates = [];

        // Template Migration PC
        $migrationTemplate = new ChecklistTemplate();
        $migrationTemplate->setNom('Checklist Migration Windows 11');
        $migrationTemplate->setDescription('Procedure complete de migration vers Windows 11');
        $migrationTemplate->setVersion(1);
        $migrationTemplate->setEtapes([
            'phases' => [
                [
                    'id' => 'phase-prep',
                    'nom' => 'Preparation',
                    'ordre' => 1,
                    'verrouillable' => true,
                    'etapes' => [
                        [
                            'id' => 'etape-1-1',
                            'titre' => 'Verifier inventaire ancien PC',
                            'description' => 'Confirmer le numero d\'inventaire et l\'etat du materiel',
                            'ordre' => 1,
                            'obligatoire' => true,
                        ],
                        [
                            'id' => 'etape-1-2',
                            'titre' => 'Sauvegarder donnees utilisateur',
                            'description' => 'Copier Documents, Bureau, Favoris sur serveur',
                            'ordre' => 2,
                            'obligatoire' => true,
                        ],
                        [
                            'id' => 'etape-1-3',
                            'titre' => 'Lister applications installees',
                            'description' => 'Relever la liste des logiciels a reinstaller',
                            'ordre' => 3,
                            'obligatoire' => true,
                        ],
                    ],
                ],
                [
                    'id' => 'phase-install',
                    'nom' => 'Installation',
                    'ordre' => 2,
                    'verrouillable' => true,
                    'etapes' => [
                        [
                            'id' => 'etape-2-1',
                            'titre' => 'Configurer nouveau PC',
                            'description' => 'Demarrage initial et jonction au domaine',
                            'ordre' => 1,
                            'obligatoire' => true,
                        ],
                        [
                            'id' => 'etape-2-2',
                            'titre' => 'Installer applications metier',
                            'description' => 'Installer les logiciels necessaires via SCCM',
                            'ordre' => 2,
                            'obligatoire' => true,
                        ],
                        [
                            'id' => 'etape-2-3',
                            'titre' => 'Restaurer donnees utilisateur',
                            'description' => 'Copier les fichiers sauvegardes vers le nouveau poste',
                            'ordre' => 3,
                            'obligatoire' => true,
                        ],
                        [
                            'id' => 'etape-2-4',
                            'titre' => 'Configurer imprimantes',
                            'description' => 'Ajouter les imprimantes reseau du service',
                            'ordre' => 4,
                            'obligatoire' => false,
                        ],
                    ],
                ],
                [
                    'id' => 'phase-valid',
                    'nom' => 'Validation',
                    'ordre' => 3,
                    'verrouillable' => false,
                    'etapes' => [
                        [
                            'id' => 'etape-3-1',
                            'titre' => 'Test avec utilisateur',
                            'description' => 'Faire valider les applications principales',
                            'ordre' => 1,
                            'obligatoire' => true,
                        ],
                        [
                            'id' => 'etape-3-2',
                            'titre' => 'Signature PV reception',
                            'description' => 'Obtenir la signature de l\'utilisateur',
                            'ordre' => 2,
                            'obligatoire' => true,
                        ],
                        [
                            'id' => 'etape-3-3',
                            'titre' => 'Recuperer ancien PC',
                            'description' => 'Etiqueter et stocker pour destruction',
                            'ordre' => 3,
                            'obligatoire' => true,
                        ],
                    ],
                ],
            ],
        ]);
        $migrationTemplate->setActif(true);
        $manager->persist($migrationTemplate);
        $templates['migration'] = $migrationTemplate;

        // Template Installation simple
        $installTemplate = new ChecklistTemplate();
        $installTemplate->setNom('Checklist Installation Logiciel');
        $installTemplate->setDescription('Procedure d\'installation standard de logiciel');
        $installTemplate->setVersion(1);
        $installTemplate->setEtapes([
            'phases' => [
                [
                    'id' => 'phase-install',
                    'nom' => 'Installation',
                    'ordre' => 1,
                    'verrouillable' => false,
                    'etapes' => [
                        [
                            'id' => 'etape-1-1',
                            'titre' => 'Verifier prerequis',
                            'description' => 'Verifier espace disque et version OS',
                            'ordre' => 1,
                            'obligatoire' => true,
                        ],
                        [
                            'id' => 'etape-1-2',
                            'titre' => 'Installer le logiciel',
                            'description' => 'Executer l\'installateur avec les options standard',
                            'ordre' => 2,
                            'obligatoire' => true,
                        ],
                        [
                            'id' => 'etape-1-3',
                            'titre' => 'Activer la licence',
                            'description' => 'Saisir la cle de licence si necessaire',
                            'ordre' => 3,
                            'obligatoire' => false,
                        ],
                        [
                            'id' => 'etape-1-4',
                            'titre' => 'Test fonctionnel',
                            'description' => 'Verifier le bon lancement de l\'application',
                            'ordre' => 4,
                            'obligatoire' => true,
                        ],
                    ],
                ],
            ],
        ]);
        $installTemplate->setActif(true);
        $manager->persist($installTemplate);
        $templates['installation'] = $installTemplate;

        return $templates;
    }

    /**
     * Cree les campagnes avec segments et operations
     *
     * @param array<string, Utilisateur> $utilisateurs
     * @param array<string, TypeOperation> $typesOperation
     * @param array<string, ChecklistTemplate> $templates
     */
    private function createCampagnes(
        ObjectManager $manager,
        array $utilisateurs,
        array $typesOperation,
        array $templates
    ): void {
        $techniciens = [$utilisateurs['karim'], $utilisateurs['tech1'], $utilisateurs['tech2'], $utilisateurs['tech3']];

        // Campagne 1 : En cours - Migration Windows 11 CPAM 92
        $campagne1 = $this->createCampagne(
            $manager,
            'Migration Windows 11 - CPAM 92',
            'Campagne de migration du parc informatique vers Windows 11. Objectif : 50 postes en janvier 2026.',
            new \DateTimeImmutable('2026-01-06'),
            new \DateTimeImmutable('2026-02-28'),
            Campagne::STATUT_EN_COURS,
            $utilisateurs['sophie'],
            $typesOperation['migration_pc'],
            $templates['migration']
        );

        $segments1 = [
            ['RDC - Accueil', 'primary', 15],
            ['Etage 1 - Prestations', 'success', 20],
            ['Etage 2 - Direction', 'warning', 15],
        ];

        $this->createSegmentsAndOperations(
            $manager,
            $campagne1,
            $segments1,
            $techniciens,
            $templates['migration'],
            true // campagne en cours = mix de statuts
        );

        // Campagne 2 : A venir - Installation Office 365
        $campagne2 = $this->createCampagne(
            $manager,
            'Deploiement Office 365 - Vague 2',
            'Installation de la suite Office 365 sur les postes restants. Formation utilisateurs incluse.',
            new \DateTimeImmutable('2026-02-01'),
            new \DateTimeImmutable('2026-03-31'),
            Campagne::STATUT_A_VENIR,
            $utilisateurs['sophie'],
            $typesOperation['installation'],
            $templates['installation']
        );

        $segments2 = [
            ['Site Nanterre', 'primary', 20],
            ['Site Colombes', 'success', 15],
            ['Site Courbevoie', 'complete', 15],
        ];

        $this->createSegmentsAndOperations(
            $manager,
            $campagne2,
            $segments2,
            $techniciens,
            $templates['installation'],
            false // campagne a venir = tout a_planifier
        );

        // Campagne 3 : Terminee - Maintenance Q4 2025
        $campagne3 = $this->createCampagne(
            $manager,
            'Maintenance preventive Q4 2025',
            'Campagne de maintenance preventive du parc informatique - 4eme trimestre 2025.',
            new \DateTimeImmutable('2025-10-01'),
            new \DateTimeImmutable('2025-12-31'),
            Campagne::STATUT_TERMINEE,
            $utilisateurs['sophie'],
            $typesOperation['maintenance'],
            null
        );

        $segments3 = [
            ['Batiment A', 'muted', 20],
            ['Batiment B', 'muted', 15],
            ['Batiment C', 'muted', 15],
        ];

        $this->createSegmentsAndOperations(
            $manager,
            $campagne3,
            $segments3,
            $techniciens,
            null,
            false, // terminee
            true   // tout realise
        );
    }

    /**
     * Cree une campagne
     */
    private function createCampagne(
        ObjectManager $manager,
        string $nom,
        string $description,
        \DateTimeImmutable $dateDebut,
        \DateTimeImmutable $dateFin,
        string $statut,
        Utilisateur $proprietaire,
        ?TypeOperation $typeOperation,
        ?ChecklistTemplate $template
    ): Campagne {
        $campagne = new Campagne();
        $campagne->setNom($nom);
        $campagne->setDescription($description);
        $campagne->setDateDebut($dateDebut);
        $campagne->setDateFin($dateFin);
        $campagne->setStatut($statut);
        $campagne->setProprietaire($proprietaire);
        $campagne->setTypeOperation($typeOperation);
        $campagne->setChecklistTemplate($template);
        $manager->persist($campagne);

        return $campagne;
    }

    /**
     * Cree les segments et operations pour une campagne
     *
     * @param array<array{0: string, 1: string, 2: int}> $segmentsData [nom, couleur, nbOperations]
     * @param array<Utilisateur> $techniciens
     */
    private function createSegmentsAndOperations(
        ObjectManager $manager,
        Campagne $campagne,
        array $segmentsData,
        array $techniciens,
        ?ChecklistTemplate $template,
        bool $mixStatuts = false,
        bool $toutRealise = false
    ): void {
        $operationCount = 0;

        foreach ($segmentsData as $ordre => $data) {
            [$nomSegment, $couleur, $nbOperations] = $data;

            $segment = new Segment();
            $segment->setNom($nomSegment);
            $segment->setCouleur($couleur);
            $segment->setCampagne($campagne);
            $segment->setOrdre($ordre);
            $manager->persist($segment);

            // Generer les operations pour ce segment
            for ($i = 1; $i <= $nbOperations; $i++) {
                $operationCount++;
                $operation = $this->createOperation(
                    $manager,
                    $campagne,
                    $segment,
                    $operationCount,
                    $techniciens,
                    $template,
                    $mixStatuts,
                    $toutRealise
                );
            }
        }
    }

    /**
     * Cree une operation
     */
    private function createOperation(
        ObjectManager $manager,
        Campagne $campagne,
        Segment $segment,
        int $numero,
        array $techniciens,
        ?ChecklistTemplate $template,
        bool $mixStatuts,
        bool $toutRealise
    ): Operation {
        $operation = new Operation();

        // Matricule unique
        $prefixe = match ($campagne->getStatut()) {
            Campagne::STATUT_EN_COURS => 'MIG',
            Campagne::STATUT_A_VENIR => 'OFF',
            default => 'MNT',
        };
        $operation->setMatricule(sprintf('%s-2026-%04d', $prefixe, $numero));

        // Nom agent fictif
        $operation->setNom($this->faker->lastName() . ' ' . $this->faker->firstName());

        // Determiner le statut
        $statut = $this->determineStatut($mixStatuts, $toutRealise, $numero);
        $operation->setStatut($statut);

        // Assignation technicien pour operations planifiees/en cours/realisees
        if (in_array($statut, [
            Operation::STATUT_PLANIFIE,
            Operation::STATUT_EN_COURS,
            Operation::STATUT_REALISE,
            Operation::STATUT_REPORTE,
            Operation::STATUT_A_REMEDIER,
        ])) {
            $technicien = $techniciens[array_rand($techniciens)];
            $operation->setTechnicienAssigne($technicien);
        }

        // Date planifiee
        if ($statut !== Operation::STATUT_A_PLANIFIER) {
            $daysOffset = $this->faker->numberBetween(-10, 30);
            $operation->setDatePlanifiee(new \DateTimeImmutable("2026-01-22 +{$daysOffset} days"));
        }

        // Date realisation si realise
        if ($statut === Operation::STATUT_REALISE) {
            $operation->setDateRealisation(new \DateTimeImmutable());
        }

        // Motif si reporte
        if ($statut === Operation::STATUT_REPORTE) {
            $motifs = [
                'Agent absent - conges',
                'Materiel non disponible',
                'Report a la demande du manager',
                'Probleme technique reseau',
            ];
            $operation->setMotifReport($motifs[array_rand($motifs)]);
        }

        // Notes si a remedier
        if ($statut === Operation::STATUT_A_REMEDIER) {
            $problemes = [
                'Erreur lors de la migration des donnees Outlook',
                'Application metier incompatible Windows 11',
                'Peripherique non reconnu apres migration',
                'Profil utilisateur corrompu',
            ];
            $operation->setNotes($problemes[array_rand($problemes)]);
        }

        // Donnees personnalisees selon le type
        $typeOp = $campagne->getTypeOperation();
        if ($typeOp !== null) {
            $donneesPerso = $this->generateDonneesPersonnalisees($typeOp, $numero);
            $operation->setDonneesPersonnalisees($donneesPerso);
        }

        $operation->setCampagne($campagne);
        $operation->setSegment($segment);
        $operation->setTypeOperation($typeOp);

        $manager->persist($operation);

        // Creer checklist instance si template et statut en cours/realise
        if ($template !== null && in_array($statut, [
            Operation::STATUT_EN_COURS,
            Operation::STATUT_REALISE,
        ])) {
            $this->createChecklistInstance($manager, $operation, $template, $statut === Operation::STATUT_REALISE);
        }

        return $operation;
    }

    /**
     * Determine le statut d'une operation selon les parametres
     */
    private function determineStatut(bool $mixStatuts, bool $toutRealise, int $numero): string
    {
        if ($toutRealise) {
            // Campagne terminee : 90% realise, 5% reporte, 5% a_remedier
            $rand = $numero % 20;
            if ($rand === 0) {
                return Operation::STATUT_REPORTE;
            }
            if ($rand === 1) {
                return Operation::STATUT_A_REMEDIER;
            }
            return Operation::STATUT_REALISE;
        }

        if (!$mixStatuts) {
            // Campagne a venir : tout a_planifier
            return Operation::STATUT_A_PLANIFIER;
        }

        // Campagne en cours : mix realiste
        $distribution = [
            Operation::STATUT_REALISE => 35,      // 35%
            Operation::STATUT_EN_COURS => 10,     // 10%
            Operation::STATUT_PLANIFIE => 30,     // 30%
            Operation::STATUT_A_PLANIFIER => 15,  // 15%
            Operation::STATUT_REPORTE => 7,       // 7%
            Operation::STATUT_A_REMEDIER => 3,    // 3%
        ];

        $rand = $this->faker->numberBetween(1, 100);
        $cumul = 0;

        foreach ($distribution as $statut => $pourcent) {
            $cumul += $pourcent;
            if ($rand <= $cumul) {
                return $statut;
            }
        }

        return Operation::STATUT_A_PLANIFIER;
    }

    /**
     * Genere les donnees personnalisees selon le type d'operation
     */
    private function generateDonneesPersonnalisees(TypeOperation $typeOp, int $numero): array
    {
        $data = [];

        $champs = $typeOp->getChampsPersonnalises() ?? [];
        foreach ($champs as $champ) {
            $code = $champ['code'];
            $data[$code] = match ($code) {
                'ancien_pc' => sprintf('INV-OLD-%06d', $numero + 10000),
                'nouveau_pc' => sprintf('INV-NEW-%06d', $numero + 20000),
                'applications_metier' => 'CRISTAL, MEDIALOG, GED',
                'logiciel' => 'Microsoft Office 365',
                'version' => '16.0.17029',
                'licence' => sprintf('XXXX-XXXX-%04d', $numero),
                'type_maintenance' => ['Nettoyage', 'Mise a jour', 'Verification complete'][array_rand(['Nettoyage', 'Mise a jour', 'Verification complete'])],
                default => $this->faker->word(),
            };
        }

        return $data;
    }

    /**
     * Cree une instance de checklist pour une operation
     */
    private function createChecklistInstance(
        ObjectManager $manager,
        Operation $operation,
        ChecklistTemplate $template,
        bool $complete
    ): void {
        $instance = new ChecklistInstance();
        $instance->createSnapshotFromTemplate($template);
        $instance->setOperation($operation);

        // Remplir la progression
        $phases = $instance->getPhases();
        $technicienId = $operation->getTechnicienAssigne()?->getId() ?? 1;

        foreach ($phases as $phase) {
            foreach ($phase['etapes'] ?? [] as $etape) {
                if ($complete) {
                    // Tout cocher
                    $instance->cocherEtape($etape['id'], $technicienId);
                } else {
                    // Cocher partiellement (50% environ)
                    if ($this->faker->boolean(50)) {
                        $instance->cocherEtape($etape['id'], $technicienId);
                    }
                }
            }
        }

        $manager->persist($instance);
        $operation->setChecklistInstance($instance);
    }
}
