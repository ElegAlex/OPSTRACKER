<?php

namespace App\Controller\Admin;

use App\Service\ConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controleur pour l'export/import de configuration.
 *
 * T-1203 / US-806 : Exporter/Importer la configuration
 * RG-100 : Export en ZIP
 * RG-101 : Import avec gestion des conflits
 */
#[Route('/admin/configuration')]
#[IsGranted('ROLE_ADMIN')]
class ConfigurationController extends AbstractController
{
    public function __construct(
        private readonly ConfigurationService $configurationService
    ) {
    }

    #[Route('', name: 'admin_configuration_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/configuration/index.html.twig', [
            'modes' => ConfigurationService::MODES,
        ]);
    }

    #[Route('/export', name: 'admin_configuration_export', methods: ['GET'])]
    public function export(): Response
    {
        try {
            $zipPath = $this->configurationService->exporter();

            $response = new BinaryFileResponse($zipPath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'opstracker_config_' . date('Y-m-d_His') . '.zip'
            );
            $response->deleteFileAfterSend(true);

            return $response;
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de l\'export : ' . $e->getMessage());
            return $this->redirectToRoute('admin_configuration_index');
        }
    }

    #[Route('/import', name: 'admin_configuration_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        $file = $request->files->get('config_file');
        $mode = $request->request->get('mode', ConfigurationService::MODE_CREER_NOUVEAUX);

        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('admin_configuration_index');
        }

        // Vérifier l'extension
        if ($file->getClientOriginalExtension() !== 'zip') {
            $this->addFlash('danger', 'Le fichier doit être un archive ZIP.');
            return $this->redirectToRoute('admin_configuration_index');
        }

        // Analyser d'abord
        $analyse = $this->configurationService->analyser($file);

        if (!$analyse['valid']) {
            foreach ($analyse['errors'] as $error) {
                $this->addFlash('danger', $error);
            }
            return $this->redirectToRoute('admin_configuration_index');
        }

        // Si analyse OK, importer
        $result = $this->configurationService->importer($file, $mode);

        if ($result['success']) {
            $total = array_sum($result['imported']);
            $this->addFlash('success', sprintf(
                'Import réussi : %d élément(s) importé(s) (%d types, %d templates).',
                $total,
                $result['imported']['types_operations'],
                $result['imported']['templates_checklists']
            ));

            foreach ($result['conflicts'] as $conflict) {
                $this->addFlash('warning', $conflict);
            }
        } else {
            foreach ($result['errors'] as $error) {
                $this->addFlash('danger', $error);
            }
        }

        return $this->redirectToRoute('admin_configuration_index');
    }
}
