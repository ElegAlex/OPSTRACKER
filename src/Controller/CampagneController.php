<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\CampagneAgentAutorise;
use App\Entity\CampagneChamp;
use App\Entity\Operation;
use App\Form\CampagneStep1Type;
use App\Form\CampagneStep2Type;
use App\Form\CampagneStep3Type;
use App\Form\CampagneStep4Type;
use App\Form\OperationType;
use App\Form\TransfertProprietaireType;
use App\Form\VisibiliteCampagneType;
use App\Form\WorkflowCampagneType;
use App\Repository\CampagneRepository;
use App\Repository\ChecklistTemplateRepository;
use App\Service\CampagneChampService;
use App\Service\CampagneService;
use App\Service\ChecklistService;
use App\Service\ExportCsvService;
use App\Service\ImportCsvService;
use App\Service\PersonnesAutoriseesService;
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
        private readonly PersonnesAutoriseesService $personnesAutoriseesService,
        private readonly \App\Service\SegmentSyncService $segmentSyncService,
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
        // RG-003 : Admin et Gestionnaire voient toutes les campagnes
        $hasFullAccess = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_GESTIONNAIRE');

        // RG-112 : Filtrage par visibilite
        $campagnesGroupees = $this->campagneService->getCampagnesVisiblesGroupedByStatut($currentUser, $hasFullAccess);
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
     * T-901 / US-203 : Creer campagne - Etape 2/4 (Upload CSV ou colonnes manuelles).
     * RG-012 : Max 100 000 lignes, encodage auto-detecte
     * RG-013 : Fichier .csv uniquement accepte
     * Creation manuelle : definir les colonnes sans import CSV
     */
    #[Route('/{id}/import', name: 'app_campagne_step2', methods: ['GET', 'POST'])]
    public function step2(Campagne $campagne, Request $request): Response
    {
        $form = $this->createForm(CampagneStep2Type::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $csvFile */
            $csvFile = $form->get('csvFile')->getData();
            $colonnesManuelles = $form->get('colonnes_manuelles')->getData();

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

            // Si pas de CSV mais colonnes manuelles definies
            if ($colonnesManuelles) {
                $lignes = array_filter(array_map('trim', explode("\n", $colonnesManuelles)));

                // Recuperer les CampagneChamp existants
                $existingChamps = [];
                foreach ($campagne->getChamps() as $champ) {
                    $existingChamps[mb_strtolower($champ->getNom())] = $champ;
                }

                $ordre = count($existingChamps);
                foreach ($lignes as $nomColonne) {
                    if (!empty($nomColonne)) {
                        $nomLower = mb_strtolower($nomColonne);

                        // Creer le CampagneChamp s'il n'existe pas
                        if (!isset($existingChamps[$nomLower])) {
                            $champ = new CampagneChamp();
                            $champ->setNom($nomColonne);
                            $champ->setOrdre($ordre++);
                            $campagne->addChamp($champ);
                            $this->entityManager->persist($champ);
                            $existingChamps[$nomLower] = $champ;
                        }
                    }
                }

                $this->entityManager->flush();

                $this->addFlash('success', sprintf('%d colonne(s) creee(s) avec succes.', count($lignes)));

                // Rediriger vers Step 4 (skip Step 3 car pas d'import)
                return $this->redirectToRoute('app_campagne_step4', ['id' => $campagne->getId()]);
            }

            // Pas de fichier ni colonnes = passer directement a l'etape 4
            return $this->redirectToRoute('app_campagne_step4', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/step2.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
            'step' => 2,
        ]);
    }

    /**
     * T-902 / US-204 : Creer campagne - Etape 3/4 (Confirmation import).
     *
     * RG-015 : TOUTES les colonnes CSV deviennent des CampagneChamp
     * RG-092 : Lignes en erreur ignorees, log genere
     *
     * Plus de mapping manuel - import automatique de toutes les colonnes.
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
        $headers = $analysis['headers'];

        $form = $this->createForm(CampagneStep3Type::class, null, [
            'csv_encoding' => $analysis['encoding'],
            'csv_separator' => $analysis['separator'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Recuperer le mapping des colonnes date/horaire/segment
            $colonneDatePlanifiee = $request->request->get('colonne_date_planifiee');
            $colonneHoraire = $request->request->get('colonne_horaire');
            $colonneSegment = $request->request->get('colonne_segment');

            // Sauvegarder le mapping dans la campagne
            if ($colonneDatePlanifiee) {
                $campagne->setColonneDatePlanifiee($colonneDatePlanifiee);
            }
            if ($colonneHoraire) {
                $campagne->setColonneHoraire($colonneHoraire);
            }
            if ($colonneSegment) {
                $campagne->setColonneSegment($colonneSegment);
            }

            // RG-015 : Creer un CampagneChamp pour CHAQUE colonne du CSV
            $customFieldsMapping = [];

            // Recuperer les CampagneChamp existants
            $existingChamps = [];
            foreach ($campagne->getChamps() as $champ) {
                $existingChamps[mb_strtolower($champ->getNom())] = $champ;
            }

            $ordre = count($existingChamps);
            foreach ($headers as $index => $header) {
                $headerLower = mb_strtolower($header);

                // Creer le CampagneChamp s'il n'existe pas
                if (!isset($existingChamps[$headerLower])) {
                    $champ = new CampagneChamp();
                    $champ->setNom($header);
                    $champ->setOrdre($ordre++);
                    $campagne->addChamp($champ);
                    $this->entityManager->persist($champ);
                    $existingChamps[$headerLower] = $champ;
                }

                // Mapping : nom du champ => index de la colonne CSV
                $customFieldsMapping[$header] = $index;
            }

            // Persister les nouveaux champs avant l'import
            $this->entityManager->flush();

            // Executer l'import avec le mapping date/horaire
            $result = $this->importCsvService->import(
                $campagne,
                $csvFilePath,
                [], // Pas de mapping systeme
                $customFieldsMapping,
                $data['csv_encoding'],
                $data['csv_separator'],
                $colonneDatePlanifiee,
                $colonneHoraire
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

            // Synchroniser les segments si une colonne segment est definie
            if ($colonneSegment) {
                $this->segmentSyncService->syncFromColonne($campagne);
            }

            return $this->redirectToRoute('app_campagne_step4', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/step3.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
            'step' => 3,
            'analysis' => $analysis,
            'headers' => $headers,
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

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        $form = $this->createForm(CampagneStep4Type::class, $campagne);
        $form->handleRequest($request);

        // Recuperer les eventuelles erreurs d'import
        $session = $request->getSession();
        $importErrors = $session->get('import_errors_' . $campagne->getId(), []);
        $session->remove('import_errors_' . $campagne->getId());

        // Recuperer les valeurs pour les filtres annuaire
        $valeursAnnuaire = $this->personnesAutoriseesService->getValeursDisponiblesAnnuaire();
        $apercuNbAgents = $this->personnesAutoriseesService->countPersonnesAutorisees($campagne);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traitement de la reservation publique
            if ($campagne->isReservationOuverte()) {
                // Traitement du mode de reservation (libre, import, annuaire)
                $reservationMode = $request->request->get('reservation_mode');
                if ($reservationMode && array_key_exists($reservationMode, Campagne::RESERVATION_MODES)) {
                    $campagne->setReservationMode($reservationMode);
                }

                // Mode annuaire : sauvegarder les filtres
                if ($reservationMode === Campagne::RESERVATION_MODE_ANNUAIRE) {
                    $filtres = $request->request->all('filtres_annuaire') ?? [];
                    // Nettoyer les filtres vides
                    $filtres = array_filter($filtres, fn ($v) => !empty($v));
                    $campagne->setReservationFiltresAnnuaire($filtres ?: null);
                }

                // Mode import : traiter le CSV
                if ($reservationMode === Campagne::RESERVATION_MODE_IMPORT) {
                    /** @var UploadedFile|null $file */
                    $file = $request->files->get('import_agents_csv');
                    if ($file) {
                        $this->importAgentsAutorises($campagne, $file);
                    }
                }

                // Generer un shareToken si pas de token
                if (!$campagne->getShareToken()) {
                    $campagne->setShareToken(substr(bin2hex(random_bytes(8)), 0, 12));
                    $campagne->setShareTokenCreatedAt(new \DateTimeImmutable());
                }
            }

            // Mettre à jour la capacité de toutes les opérations existantes
            $newCapacite = $campagne->getCapaciteParDefaut();
            foreach ($campagne->getOperations() as $operation) {
                $operation->setCapacite($newCapacite);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Configuration de la campagne mise a jour.');

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/step4.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
            'step' => 4,
            'import_errors' => $importErrors,
            'valeursAnnuaire' => $valeursAnnuaire,
            'apercuNbAgents' => $apercuNbAgents,
        ]);
    }

    /**
     * Importe les agents autorises depuis un fichier CSV.
     */
    private function importAgentsAutorises(Campagne $campagne, UploadedFile $file): void
    {
        // Supprimer les anciens agents autorises
        foreach ($campagne->getAgentsAutorises() as $agent) {
            $this->entityManager->remove($agent);
        }
        $campagne->clearAgentsAutorises();

        // Lire le CSV
        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            return;
        }

        // Detecter le separateur
        $firstLine = fgets($handle);
        rewind($handle);
        $separator = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $headers = fgetcsv($handle, 0, $separator);
        if (!$headers) {
            fclose($handle);

            return;
        }
        $headers = array_map('strtolower', array_map('trim', $headers));

        $count = 0;
        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            if (count($row) < count($headers)) {
                continue;
            }

            $data = array_combine($headers, $row);

            $nomPrenom = $data['nom_prenom'] ?? $data['nomprenom'] ?? $data['nom'] ?? null;
            if (!$nomPrenom || trim($nomPrenom) === '') {
                continue;
            }

            $agent = new CampagneAgentAutorise();
            $agent->setCampagne($campagne);
            $agent->setNomPrenom(trim($nomPrenom));

            // Identifiant : email si present, sinon nom_prenom
            $email = isset($data['email']) ? trim($data['email']) : null;
            $agent->setIdentifiant($email ?: trim($nomPrenom));
            $agent->setEmail($email);

            $agent->setService(isset($data['service']) ? trim($data['service']) : null);
            $agent->setSite(isset($data['site']) ? trim($data['site']) : null);

            $campagne->addAgentAutorise($agent);
            $this->entityManager->persist($agent);
            ++$count;
        }

        fclose($handle);
    }

    /**
     * Redirection vers le nouveau dashboard.
     *
     * @deprecated Utiliser app_dashboard_campagne directement
     */
    #[Route('/{id}', name: 'app_campagne_show', methods: ['GET'])]
    public function show(Campagne $campagne): Response
    {
        return $this->redirectToRoute('app_dashboard_campagne', [
            'id' => $campagne->getId(),
        ], 301);
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
            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        $operation = new Operation();
        $operation->setCampagne($campagne);
        $operation->setTypeOperation($campagne->getTypeOperation());

        $form = $this->createForm(OperationType::class, $operation, [
            'campagne' => $campagne,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traiter les champs personnalises (CampagneChamp)
            // Toute colonne = un CampagneChamp, pas de filtrage
            $donneesPersonnalisees = [];
            foreach ($campagne->getChamps() as $champ) {
                $champNom = $champ->getNom();
                $fieldName = CampagneChampService::normalizeFieldName($champNom);

                if ($form->has($fieldName)) {
                    $valeur = $form->get($fieldName)->getData();
                    if ($valeur !== null && $valeur !== '') {
                        $donneesPersonnalisees[$champNom] = $valeur;
                    }
                }
            }
            if (!empty($donneesPersonnalisees)) {
                $operation->setDonneesPersonnalisees($donneesPersonnalisees);
            }

            $this->entityManager->persist($operation);
            $this->entityManager->flush();

            $this->addFlash('success', 'Operation ajoutee avec succes.');

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/operation_new.html.twig', [
            'campagne' => $campagne,
            'operation' => $operation,
            'form' => $form,
            'champs' => $campagne->getChamps(),
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
            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        if ($this->campagneService->appliquerTransition($campagne, $transition)) {
            $this->addFlash('success', 'Statut de la campagne mis a jour.');
        } else {
            $this->addFlash('danger', 'Cette transition n\'est pas disponible.');
        }

        return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
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
            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        // Seul le proprietaire ou un admin peut transferer
        $currentUser = $this->getUser();
        if ($campagne->getProprietaire() !== $currentUser && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Seul le proprietaire ou un administrateur peut transferer la propriete.');
            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
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

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/proprietaire.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
        ]);
    }

    /**
     * T-1104 / US-212 : Configurer le workflow d'une campagne.
     * Architecture retroactive :
     * - Si pas de checklist → afficher dropdown pour choisir un template
     * - Si checklist existe → interface de gestion (desactiver/reactiver/ajouter etapes)
     * RG-016 : Interdit si campagne archivee
     */
    #[Route('/{id}/workflow', name: 'app_campagne_workflow', methods: ['GET', 'POST'])]
    public function workflow(
        Campagne $campagne,
        Request $request,
        ChecklistService $checklistService,
        ChecklistTemplateRepository $templateRepository
    ): Response {
        // RG-016 : Campagne archivee = lecture seule
        if ($campagne->isReadOnly()) {
            $this->addFlash('danger', 'Cette campagne est archivee et ne peut pas etre modifiee.');

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        // Seul le proprietaire ou un admin peut modifier le workflow
        $currentUser = $this->getUser();
        if ($campagne->getProprietaire() !== $currentUser && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Seul le proprietaire ou un administrateur peut modifier le workflow.');

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        // CAS 1 : Pas de checklist configuree - afficher selection de template
        if (!$campagne->hasChecklistStructure()) {
            $templates = $templateRepository->findActifs();

            if ($request->isMethod('POST')) {
                $templateId = $request->request->get('template_id');

                if ($templateId) {
                    $template = $templateRepository->find($templateId);
                    if ($template) {
                        $checklistService->copierTemplateVersCampagne($campagne, $template);
                        $this->addFlash('success', 'Checklist initialisee depuis le template "'.$template->getNom().'".');

                        return $this->redirectToRoute('app_campagne_workflow', ['id' => $campagne->getId()]);
                    }
                }

                $this->addFlash('danger', 'Veuillez selectionner un template valide.');
            }

            return $this->render('campagne/workflow_select_template.html.twig', [
                'campagne' => $campagne,
                'templates' => $templates,
            ]);
        }

        // CAS 2 : Checklist existe - interface de gestion des etapes
        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            $etapeId = $request->request->get('etape_id');
            $phaseId = $request->request->get('phase_id');

            try {
                switch ($action) {
                    case 'desactiver':
                        $checklistService->desactiverEtape($campagne, $etapeId);
                        $this->addFlash('success', 'Etape desactivee.');
                        break;

                    case 'reactiver':
                        $checklistService->reactiverEtape($campagne, $etapeId);
                        $this->addFlash('success', 'Etape reactivee.');
                        break;

                    case 'ajouter':
                        $titre = trim($request->request->get('titre', ''));
                        $description = trim($request->request->get('description', '')) ?: null;
                        $obligatoire = $request->request->getBoolean('obligatoire', true);

                        if ($titre) {
                            $checklistService->ajouterEtapeCampagne(
                                $campagne,
                                $phaseId,
                                $titre,
                                $description,
                                $obligatoire
                            );
                            $this->addFlash('success', 'Etape "'.$titre.'" ajoutee.');
                        } else {
                            $this->addFlash('danger', 'Le titre de l\'etape est obligatoire.');
                        }
                        break;

                    case 'mapping':
                        $champCible = trim($request->request->get('champ_cible', ''));
                        $campagne->setChampCibleForEtape($etapeId, $champCible ?: null);
                        $this->entityManager->flush();
                        $this->addFlash('success', $champCible ? 'Champ de saisie "'.$champCible.'" configure.' : 'Champ de saisie supprime.');
                        break;
                }
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }

            return $this->redirectToRoute('app_campagne_workflow', ['id' => $campagne->getId()]);
        }

        // Recuperer les champs de la campagne pour le mapping
        $champs = $campagne->getChamps();

        return $this->render('campagne/workflow_manage.html.twig', [
            'campagne' => $campagne,
            'structure' => $campagne->getChecklistStructure(),
            'champs' => $champs,
            'mapping' => $campagne->getChecklistMapping() ?? [],
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
            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        // Seul le proprietaire ou un admin peut modifier la visibilite
        $currentUser = $this->getUser();
        if ($campagne->getProprietaire() !== $currentUser && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Seul le proprietaire ou un administrateur peut modifier la visibilite.');
            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        $form = $this->createForm(VisibiliteCampagneType::class, $campagne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Visibilite mise a jour.');

            return $this->redirectToRoute('app_dashboard_campagne', ['id' => $campagne->getId()]);
        }

        return $this->render('campagne/visibilite.html.twig', [
            'campagne' => $campagne,
            'form' => $form,
        ]);
    }
}
