<?php

namespace App\Controller;

use App\Entity\Campagne;
use App\Entity\Document;
use App\Entity\Utilisateur;
use App\Form\DocumentUploadType;
use App\Repository\CampagneRepository;
use App\Service\DocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour la gestion des documents.
 *
 * T-1005 : Voir la liste des documents
 * T-1006 : Uploader un document (50Mo max)
 * T-1007 : Lier un document a une campagne
 * T-1008 : Supprimer un document
 */
#[Route('/campagnes/{campagneId}/documents', requirements: ['campagneId' => '\d+'])]
#[IsGranted('ROLE_GESTIONNAIRE')]
class DocumentController extends AbstractController
{
    public function __construct(
        private DocumentService $documentService,
        private CampagneRepository $campagneRepository,
    ) {
    }

    /**
     * T-1005 : Voir la liste des documents d'une campagne
     */
    #[Route('', name: 'app_document_index', methods: ['GET'])]
    public function index(int $campagneId): Response
    {
        $campagne = $this->getCampagne($campagneId);
        $documents = $this->documentService->getDocumentsByCampagne($campagne);
        $stats = $this->documentService->getStatistiques($campagne);

        return $this->render('document/index.html.twig', [
            'campagne' => $campagne,
            'documents' => $documents,
            'stats' => $stats,
            'types' => Document::TYPES,
        ]);
    }

    /**
     * T-1006 : Uploader un document (50Mo max)
     * T-1007 : Lier un document a une campagne (automatique via route)
     */
    #[Route('/upload', name: 'app_document_upload', methods: ['GET', 'POST'])]
    public function upload(int $campagneId, Request $request): Response
    {
        $campagne = $this->getCampagne($campagneId);

        // Verifier que la campagne n'est pas archivee
        if ($campagne->isReadOnly()) {
            $this->addFlash('danger', 'Impossible d\'ajouter un document a une campagne archivee.');
            return $this->redirectToRoute('app_document_index', ['campagneId' => $campagneId]);
        }

        $form = $this->createForm(DocumentUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $file = $data['file'];

            try {
                /** @var Utilisateur $user */
                $user = $this->getUser();

                $document = $this->documentService->upload(
                    $file,
                    $campagne,
                    $user,
                    $data['type'],
                    $data['description']
                );

                // Avertissement pour les fichiers executables (RG-050)
                if ($document->isScript()) {
                    $this->addFlash('warning', sprintf(
                        'Attention : Le fichier %s est un fichier executable. Assurez-vous de sa provenance.',
                        $document->getNomOriginal()
                    ));
                }

                $this->addFlash('success', sprintf('Document "%s" uploade avec succes.', $document->getNomOriginal()));
                return $this->redirectToRoute('app_document_index', ['campagneId' => $campagneId]);

            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
                // Redirection obligatoire pour Turbo Drive
                return $this->redirectToRoute('app_document_upload', ['campagneId' => $campagneId]);
            }
        }

        // Status 422 pour les erreurs de validation (compatibilité Turbo)
        $response = new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);

        return $this->render('document/upload.html.twig', [
            'campagne' => $campagne,
            'form' => $form->createView(),
        ], $response);
    }

    /**
     * Telecharger un document
     */
    #[Route('/{id}/download', name: 'app_document_download', methods: ['GET'])]
    public function download(int $campagneId, Document $document): Response
    {
        $campagne = $this->getCampagne($campagneId);

        // Verifier que le document appartient a la campagne
        if ($document->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Document non trouve.');
        }

        $filePath = $this->documentService->getFilePath($document);

        if (!$this->documentService->fileExists($document)) {
            throw $this->createNotFoundException('Fichier non trouve.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getNomOriginal()
        );

        return $response;
    }

    /**
     * T-1008 : Supprimer un document
     */
    #[Route('/{id}/delete', name: 'app_document_delete', methods: ['POST'])]
    public function delete(int $campagneId, Document $document, Request $request): Response
    {
        $campagne = $this->getCampagne($campagneId);

        // Verifier que le document appartient a la campagne
        if ($document->getCampagne()->getId() !== $campagne->getId()) {
            throw $this->createNotFoundException('Document non trouve.');
        }

        // Verifier que la campagne n'est pas archivee
        if ($campagne->isReadOnly()) {
            $this->addFlash('danger', 'Impossible de supprimer un document d\'une campagne archivee.');
            return $this->redirectToRoute('app_document_index', ['campagneId' => $campagneId]);
        }

        // Verifier le token CSRF
        if (!$this->isCsrfTokenValid('delete-document-' . $document->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de securite invalide.');
            return $this->redirectToRoute('app_document_index', ['campagneId' => $campagneId]);
        }

        $nomDocument = $document->getNomOriginal();
        $this->documentService->delete($document);

        $this->addFlash('success', sprintf('Document "%s" supprime avec succes.', $nomDocument));
        return $this->redirectToRoute('app_document_index', ['campagneId' => $campagneId]);
    }

    private function getCampagne(int $id): Campagne
    {
        $campagne = $this->campagneRepository->find($id);
        if (!$campagne) {
            throw $this->createNotFoundException('Campagne non trouvee.');
        }
        return $campagne;
    }
}
