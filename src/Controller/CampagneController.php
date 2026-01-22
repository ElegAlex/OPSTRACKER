<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Form\CampagneStep1Type;
use App\Form\CampagneStep2Type;
use App\Form\CampagneStep3Type;
use App\Form\CampagneStep4Type;
use App\Form\OperationType;
use App\Form\TransfertProprietaireType;
use App\Form\VisibiliteCampagneType;
use App\Repository\CampagneRepository;
use App\Service\CampagneService;
use App\Service\ExportCsvService;
use App\Service\ImportCsvService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour la gestion des campagnes (vue Sophie).
 *
 * User Stories :
 * - US-201 : Voir la liste des campagnes (T-301)
 * - US-202 : Creer campagne etape 1/4 (T-302)
 * - US-203 : Creer campagne etape 2/4 - Upload CSV (T-901)
 * - US-204 : Creer campagne etape 3/4 - Mapping (T-902)
 * - US-205 : Creer campagne etape 4/4 (T-303)
 * - US-206 : Ajouter une operation manuellement (T-304)
 */
#[Route('/campagnes')]
#[IsGranted('ROLE_USER')]
class CampagneController extends AbstractController
{
    public function __construct(
        private readonly CampagneService $campagneService,
        private readonly CampagneRepository $campagneRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportCsvService $importCsvService,
        private readonly ExportCsvService $exportCsvService,
    ) {
    }

    /**
     * T-301 / US-201 : Liste des campagnes groupee par statut (portfolio).
     * RG-010 : 5 statuts avec couleurs distinctes
     * RG-112 : Filtrage par visibilite selon l'utilisateur
     */
    #[Route('', name: 'app_campagne_index', methods: ['GET'])]
    public function index(): Response
    {
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // RG-112 : Filtrage par visibilite
        $campagnesGroupees = $this->campagneService->getCampagnesVisiblesGroupedByStatut($currentUser, $isAdmin);
        $statistiques = $this->campagneService->getStatistiquesGlobales();

        // Calculer les stats par campagne pour l'affichage
        $statsParCampagne = [];
        foreach ($campagnesGroupees as $statut => $data) {
            foreach ($data['campagnes'] as $campagne) {
                $statsParCampagne[$campagne->getId()] = $this->campagneService->getStatistiquesCampagne($campagne);
            }
        }

        return $this->render('campagne/index.html.twig', [
            'campagnes_groupees' => $campagnesGroupees,
            'statistiques' => $statistiques,
            'stats_par_campagne' => $statsParCampagne,
        ]);
    }

    /**
     * T-302 / US-202 : Creer campagne - Etape 1/4 (Infos generales).
     * RG-011 : Nom + Dates obligatoires
     * RG-111 : Le createur est proprietaire par defaut
     */
    #[Route('/nouvelle', name: 'app_campagne_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $campagne = new Campagne();

        $form = $this->createForm(CampagneStep1Type::class, $campagne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // RG-111 : Le createur est automatiquement proprietaire
            $campagne->setProprietaire($this->getUser());

            $this->entityManager->persist($campagne);
            $this->entityManager->flush();

            $this->addFlash('success', 'Campagne creee avec succes.');

            return $this->redirectToRoute('app_campagne_step2', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/new.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
            'step' => 1,
        ]);
    }

    /**
     * T-901 / US-203 : Creer campagne - Etape 2/4 (Upload CSV).
     * RG-012 : Max 100 000 lignes, encodage auto-detecte
     * RG-013 : Fichier .csv uniquement accepte
     */
    #[Route('/{id}/import', name: 'app_campagne_step2', methods: ['GET', 'POST'])]
    public function step2(Campagne $campagne, Request $request): Response
    {
        $form = $this->createForm(CampagneStep2Type::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $csvFile */
            $csvFile = $form->get('csvFile')->getData();

            if ($csvFile) {
                // RG-013 : Valider le fichier
                $validation = $this->importCsvService->validateFile($csvFile);
                if (!$validation['valid']) {
                    $this->addFlash('danger', $validation['error']);
                    return $this->redirectToRoute('app_campagne_step2', ['id' => $campagne->getId()]);
                }

                // Deplacer le fichier temporairement
                $tempDir = sys_get_temp_dir() . '/opstracker_imports';
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                $tempFilename = sprintf('%s_%s.csv', $campagne->getId(), uniqid());
                $csvFile->move($tempDir, $tempFilename);

                // Stocker le chemin en session
                $request->getSession()->set('csv_import_file_' . $campagne->getId(), $tempDir . '/' . $tempFilename);

                return $this->redirectToRoute('app_campagne_step3', ['id' => $campagne->getId()]);
            }

            // Pas de fichier = passer directement a l'etape 4
            return $this->redirectToRoute('app_campagne_step4', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/step2.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
            'step' => 2,
        ]);
    }

    /**
     * T-902 / US-204 : Creer campagne - Etape 3/4 (Mapping colonnes).
     * RG-012 : Mapping colonnes CSV vers champs Operation
     * RG-092 : Lignes en erreur ignorees, log genere
     * RG-093 : Segments auto-crees si colonne mappee
     */
    #[Route('/{id}/mapping', name: 'app_campagne_step3', methods: ['GET', 'POST'])]
    public function step3(Campagne $campagne, Request $request): Response
    {
        $session = $request->getSession();
        $csvFilePath = $session->get('csv_import_file_' . $campagne->getId());

        if (!$csvFilePath || !file_exists($csvFilePath)) {
            $this->addFlash('danger', 'Aucun fichier CSV trouve. Veuillez recommencer l\'import.');
            return $this->redirectToRoute('app_campagne_step2', ['id' => $campagne->getId()]);
        }

        // Analyser le fichier CSV
        $analysis = $this->importCsvService->analyzeFile($csvFilePath);
        $suggestedMapping = $this->importCsvService->suggestMapping($analysis['headers']);

        $form = $this->createForm(CampagneStep3Type::class, null, [
            'csv_headers' => $analysis['headers'],
            'suggested_mapping' => $suggestedMapping,
            'csv_encoding' => $analysis['encoding'],
            'csv_separator' => $analysis['separator'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Construire le mapping
            $mapping = [
                'matricule' => $data['mapping_matricule'],
                'nom' => $data['mapping_nom'],
                'segment' => $data['mapping_segment'],
                'notes' => $data['mapping_notes'],
                'date_planifiee' => $data['mapping_date_planifiee'],
            ];

            // Validation : matricule et nom obligatoires
            if ($mapping['matricule'] === null || $mapping['nom'] === null) {
                $this->addFlash('danger', 'Les colonnes Matricule et Nom sont obligatoires.');
                return $this->render('campagne/step3.html.twig', [
                    'campagne' => $campagne,
                    'form' => $form,
                    'step' => 3,
                    'analysis' => $analysis,
                ]);
            }

            // Executer l'import
            $result = $this->importCsvService->import(
                $campagne,
                $csvFilePath,
                $mapping,
                [],
                $data['csv_encoding'],
                $data['csv_separator']
            );

            // Nettoyer le fichier temporaire
            @unlink($csvFilePath);
            $session->remove('csv_import_file_' . $campagne->getId());

            // Messages de resultat
            if ($result->isSuccess()) {
                $this->addFlash('success', sprintf(
                    '%d operation(s) importee(s) avec succes.',
                    $result->getImportedCount()
                ));
            }

            if ($result->hasErrors()) {
                $this->addFlash('warning', sprintf(
                    '%d ligne(s) ignoree(s) avec erreurs. Consultez les details ci-dessous.',
                    $result->getErrorCount()
                ));
                // Stocker les erreurs en session pour affichage
                $session->set('import_errors_' . $campagne->getId(), $result->getErrors());
            }

            return $this->redirectToRoute('app_campagne_step4', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/step3.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
            'step' => 3,
            'analysis' => $analysis,
        ]);
    }

    /**
     * T-303 / US-205 : Creer campagne - Etape 4/4 (Workflow & Template).
     * RG-014 : Association TypeOperation + ChecklistTemplate
     * RG-016 : Interdit si campagne archivee
     */
    #[Route('/{id}/configurer', name: 'app_campagne_step4', methods: ['GET', 'POST'])]
    public function step4(Campagne $campagne, Request $request): Response
    {
        // RG-016 : Campagne archivee = lecture seule
        if ($campagne->isReadOnly()) {
            $this->addFlash('danger', 'Cette campagne est archivee et ne peut pas etre modifiee.');
            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        $form = $this->createForm(CampagneStep4Type::class, $campagne);
        $form->handleRequest($request);

        // Recuperer les eventuelles erreurs d'import
        $session = $request->getSession();
        $importErrors = $session->get('import_errors_' . $campagne->getId(), []);
        $session->remove('import_errors_' . $campagne->getId());

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Configuration de la campagne mise a jour.');

            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/step4.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
            'step' => 4,
            'import_errors' => $importErrors,
        ]);
    }

    /**
     * Detail d'une campagne.
     */
    #[Route('/{id}', name: 'app_campagne_show', methods: ['GET'])]
    public function show(Campagne $campagne): Response
    {
        $statistiques = $this->campagneService->getStatistiquesCampagne($campagne);
        $transitions = $this->campagneService->getTransitionsDisponibles($campagne);

        return $this->render('campagne/show.html.twig', [
            'campagne' => $campagne,
            'statistiques' => $statistiques,
            'transitions' => $transitions,
        ]);
    }

    /**
     * T-304 / US-206 : Ajouter une operation manuellement.
     * RG-014 : Statut initial = "A planifier"
     * RG-015 : Donnees personnalisees JSONB
     * RG-016 : Interdit si campagne archivee
     */
    #[Route('/{id}/operations/nouvelle', name: 'app_campagne_operation_new', methods: ['GET', 'POST'])]
    public function newOperation(Campagne $campagne, Request $request): Response
    {
        // RG-016 : Campagne archivee = lecture seule
        if ($campagne->isReadOnly()) {
            $this->addFlash('danger', 'Cette campagne est archivee et ne peut pas etre modifiee.');
            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        $operation = new Operation();
        $operation->setCampagne($campagne);
        $operation->setTypeOperation($campagne->getTypeOperation());

        $form = $this->createForm(OperationType::class, $operation, [
            'campagne' => $campagne,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($operation);
            $this->entityManager->flush();

            $this->addFlash('success', 'Operation ajoutee avec succes.');

            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/operation_new.html.twig', [
            'campagne' => $campagne,
            'operation' => $operation,
            'form' => $form,
        ]);
    }

    /**
     * Applique une transition de workflow.
     */
    #[Route('/{id}/transition/{transition}', name: 'app_campagne_transition', methods: ['POST'])]
    public function transition(Campagne $campagne, string $transition, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('campagne_transition_' . $campagne->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        if ($this->campagneService->appliquerTransition($campagne, $transition)) {
            $this->addFlash('success', 'Statut de la campagne mis a jour.');
        } else {
            $this->addFlash('danger', 'Cette transition n\'est pas disponible.');
        }

        return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
    }

    /**
     * Supprime une campagne et toutes ses operations.
     */
    #[Route('/{id}/supprimer', name: 'app_campagne_delete', methods: ['POST'])]
    public function delete(Campagne $campagne, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('campagne_delete_' . $campagne->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_campagne_index');
        }

        $nom = $campagne->getNom();

        $this->entityManager->remove($campagne);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Campagne "%s" supprimee.', $nom));

        return $this->redirectToRoute('app_campagne_index');
    }

    /**
     * T-906 / US-307 : Exporter les operations d'une campagne en CSV.
     */
    #[Route('/{id}/export', name: 'app_campagne_export', methods: ['GET'])]
    public function export(Campagne $campagne, Request $request): StreamedResponse
    {
        // Filtres optionnels depuis les parametres de requete
        $filters = [];
        if ($request->query->has('statut')) {
            $filters['statut'] = $request->query->get('statut');
        }
        if ($request->query->has('segment')) {
            $filters['segment'] = $request->query->get('segment');
        }

        return $this->exportCsvService->exportCampagne($campagne, null, $filters);
    }

    /**
     * T-1102 / US-210 : Transferer la propriete d'une campagne.
     * RG-111 : Transfert de propriete possible
     * RG-016 : Interdit si campagne archivee
     */
    #[Route('/{id}/proprietaire', name: 'app_campagne_proprietaire', methods: ['GET', 'POST'])]
    public function transfertProprietaire(Campagne $campagne, Request $request): Response
    {
        // RG-016 : Campagne archivee = lecture seule
        if ($campagne->isReadOnly()) {
            $this->addFlash('danger', 'Cette campagne est archivee et ne peut pas etre modifiee.');
            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        // Seul le proprietaire ou un admin peut transferer
        $currentUser = $this->getUser();
        if ($campagne->getProprietaire() !== $currentUser && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Seul le proprietaire ou un administrateur peut transferer la propriete.');
            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        $form = $this->createForm(TransfertProprietaireType::class, null, [
            'current_proprietaire' => $campagne->getProprietaire(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nouveauProprietaire = $form->get('nouveauProprietaire')->getData();
            $ancienProprietaire = $campagne->getProprietaire();

            $campagne->setProprietaire($nouveauProprietaire);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf(
                'Propriete transferee a %s %s.',
                $nouveauProprietaire->getPrenom(),
                $nouveauProprietaire->getNom()
            ));

            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/proprietaire.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
        ]);
    }

    /**
     * T-1103 / US-211 : Configurer la visibilite d'une campagne.
     * RG-112 : Visibilite par defaut restreinte
     * RG-016 : Interdit si campagne archivee
     */
    #[Route('/{id}/visibilite', name: 'app_campagne_visibilite', methods: ['GET', 'POST'])]
    public function visibilite(Campagne $campagne, Request $request): Response
    {
        // RG-016 : Campagne archivee = lecture seule
        if ($campagne->isReadOnly()) {
            $this->addFlash('danger', 'Cette campagne est archivee et ne peut pas etre modifiee.');
            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        // Seul le proprietaire ou un admin peut modifier la visibilite
        $currentUser = $this->getUser();
        if ($campagne->getProprietaire() !== $currentUser && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Seul le proprietaire ou un administrateur peut modifier la visibilite.');
            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        $form = $this->createForm(VisibiliteCampagneType::class, $campagne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Visibilite mise a jour.');

            return $this->redirectToRoute('app_campagne_show', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/visibilite.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
        ]);
    }
}
