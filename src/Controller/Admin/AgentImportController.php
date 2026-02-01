<?php

namespace App\Controller\Admin;

use App\Entity\Agent;
use App\Repository\AgentRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AgentImportController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgentRepository $agentRepository,
        private AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[Route('/admin/agents/import', name: 'admin_agent_import', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            /** @var UploadedFile|null $file */
            $file = $request->files->get('csv_file');
            $updateExisting = $request->request->getBoolean('update_existing');

            if (!$file) {
                $this->addFlash('danger', 'Veuillez selectionner un fichier CSV.');

                return $this->redirectToRoute('admin_agent_import');
            }

            $result = $this->processImport($file, $updateExisting);

            if ($result['success']) {
                $message = sprintf(
                    '%d agent(s) cree(s), %d mis a jour, %d ignore(s).',
                    $result['created'],
                    $result['updated'],
                    $result['skipped']
                );
                $this->addFlash('success', $message);

                if (!empty($result['errors'])) {
                    $this->addFlash('warning', count($result['errors']) . ' erreur(s) rencontree(s).');
                }

                $url = $this->adminUrlGenerator
                    ->setController(AgentCrudController::class)
                    ->setAction(Crud::PAGE_INDEX)
                    ->generateUrl();

                return $this->redirect($url);
            }

            $this->addFlash('danger', $result['message']);
        }

        return $this->render('admin/agent/import.html.twig');
    }

    #[Route('/admin/agents/import/template', name: 'admin_agent_import_template', methods: ['GET'])]
    public function downloadTemplate(): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // Headers
            fputcsv($handle, [
                'matricule',
                'email',
                'nom',
                'prenom',
                'service',
                'site',
                'role',
                'type_contrat',
            ], ';');

            // Exemple
            fputcsv($handle, [
                'A001',
                'jean.dupont@exemple.fr',
                'Dupont',
                'Jean',
                'Prestations Maladie',
                'Siege',
                'agent',
                'CDI',
            ], ';');

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="modele_agents.csv"');

        return $response;
    }

    private function processImport(UploadedFile $file, bool $updateExisting): array
    {
        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            return ['success' => false, 'message' => 'Impossible d\'ouvrir le fichier.'];
        }

        // Detecter le separateur
        $firstLine = fgets($handle);
        rewind($handle);
        $separator = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        // Headers
        $headers = fgetcsv($handle, 0, $separator);
        if (!$headers) {
            fclose($handle);

            return ['success' => false, 'message' => 'Fichier CSV vide ou invalide.'];
        }
        $headers = array_map('strtolower', array_map('trim', $headers));

        // Verifier colonnes obligatoires
        $required = ['matricule', 'email', 'nom', 'prenom'];
        $missing = array_diff($required, $headers);
        if (!empty($missing)) {
            fclose($handle);

            return [
                'success' => false,
                'message' => 'Colonnes manquantes : ' . implode(', ', $missing),
            ];
        }

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

            $data = array_combine($headers, array_map('trim', $row));

            // Verifier champs obligatoires
            if (empty($data['matricule']) || empty($data['email']) || empty($data['nom']) || empty($data['prenom'])) {
                $errors[] = "Ligne $lineNumber : champs obligatoires manquants";
                continue;
            }

            // Chercher si existe
            $agent = $this->agentRepository->findOneByMatricule($data['matricule']);

            if ($agent && !$updateExisting) {
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
                $agent->setMatricule($data['matricule']);
                $agent->setEmail($data['email']);
                $agent->setNom($data['nom']);
                $agent->setPrenom($data['prenom']);
                $agent->setService($data['service'] ?? null);
                $agent->setSite($data['site'] ?? null);
                $agent->setRole($data['role'] ?? null);
                $agent->setTypeContrat($data['typecontrat'] ?? $data['type_contrat'] ?? null);

                $this->em->persist($agent);
            } catch (\Exception $e) {
                $errors[] = "Ligne $lineNumber : " . $e->getMessage();
            }
        }

        fclose($handle);

        try {
            $this->em->flush();
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erreur de sauvegarde : ' . $e->getMessage()];
        }

        return [
            'success' => true,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}
