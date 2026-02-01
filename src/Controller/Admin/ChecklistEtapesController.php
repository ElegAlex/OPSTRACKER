<?php

namespace App\Controller\Admin;

use App\Entity\ChecklistTemplate;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller pour gerer les phases et etapes d'un ChecklistTemplate.
 * Interface native sans dependance JS externe.
 */
#[IsGranted('ROLE_GESTIONNAIRE')]
#[Route('/admin/checklist')]
class ChecklistEtapesController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentRepository $documentRepo
    ) {
    }

    /**
     * Page principale : affiche phases et etapes du template.
     */
    #[Route('/{id}/etapes', name: 'admin_checklist_etapes')]
    public function index(ChecklistTemplate $template): Response
    {
        return $this->render('admin/checklist/etapes.html.twig', [
            'template' => $template,
            'phases' => $template->getPhases(),
            'documents' => $this->documentRepo->findBy([], ['nomOriginal' => 'ASC']),
        ]);
    }

    // ========== GESTION DES PHASES ==========

    /**
     * Ajouter une phase.
     */
    #[Route('/{id}/phase/ajouter', name: 'admin_checklist_phase_add', methods: ['POST'])]
    public function ajouterPhase(ChecklistTemplate $template, Request $request): Response
    {
        $etapes = $template->getEtapes();
        $phases = $etapes['phases'] ?? [];

        // Generer un ID unique
        $maxId = 0;
        foreach ($phases as $phase) {
            $num = (int) str_replace('phase-', '', $phase['id'] ?? '0');
            $maxId = max($maxId, $num);
        }
        $newId = 'phase-' . ($maxId + 1);

        $phases[] = [
            'id' => $newId,
            'nom' => $request->request->get('nom', 'Nouvelle phase'),
            'ordre' => count($phases) + 1,
            'verrouillable' => (bool) $request->request->get('verrouillable', false),
            'etapes' => [],
        ];

        $etapes['phases'] = $phases;
        $template->setEtapes($etapes);
        $template->incrementVersion();
        $this->em->flush();

        $this->addFlash('success', 'Phase ajoutee');

        return $this->redirectToRoute('admin_checklist_etapes', ['id' => $template->getId()]);
    }

    /**
     * Modifier une phase.
     */
    #[Route('/{id}/phase/{phaseId}/modifier', name: 'admin_checklist_phase_edit', methods: ['POST'])]
    public function modifierPhase(ChecklistTemplate $template, string $phaseId, Request $request): Response
    {
        $etapes = $template->getEtapes();

        foreach ($etapes['phases'] as &$phase) {
            if ($phase['id'] === $phaseId) {
                $phase['nom'] = $request->request->get('nom', $phase['nom']);
                $phase['verrouillable'] = (bool) $request->request->get('verrouillable', false);
                break;
            }
        }

        $template->setEtapes($etapes);
        $template->incrementVersion();
        $this->em->flush();

        $this->addFlash('success', 'Phase modifiee');

        return $this->redirectToRoute('admin_checklist_etapes', ['id' => $template->getId()]);
    }

    /**
     * Supprimer une phase (et ses etapes).
     */
    #[Route('/{id}/phase/{phaseId}/supprimer', name: 'admin_checklist_phase_delete', methods: ['POST'])]
    public function supprimerPhase(ChecklistTemplate $template, string $phaseId): Response
    {
        $etapes = $template->getEtapes();

        $etapes['phases'] = array_values(array_filter(
            $etapes['phases'] ?? [],
            fn ($p) => $p['id'] !== $phaseId
        ));

        // Reindexer les ordres
        foreach ($etapes['phases'] as $i => &$phase) {
            $phase['ordre'] = $i + 1;
        }

        $template->setEtapes($etapes);
        $template->incrementVersion();
        $this->em->flush();

        $this->addFlash('success', 'Phase supprimee');

        return $this->redirectToRoute('admin_checklist_etapes', ['id' => $template->getId()]);
    }

    /**
     * Monter une phase.
     */
    #[Route('/{id}/phase/{phaseId}/monter', name: 'admin_checklist_phase_up', methods: ['POST'])]
    public function monterPhase(ChecklistTemplate $template, string $phaseId): Response
    {
        $etapes = $template->getEtapes();
        $phases = $etapes['phases'] ?? [];

        $index = null;
        foreach ($phases as $i => $phase) {
            if ($phase['id'] === $phaseId) {
                $index = $i;
                break;
            }
        }

        if (null !== $index && $index > 0) {
            [$phases[$index - 1], $phases[$index]] = [$phases[$index], $phases[$index - 1]];
            foreach ($phases as $i => &$phase) {
                $phase['ordre'] = $i + 1;
            }
            $etapes['phases'] = $phases;
            $template->setEtapes($etapes);
            $template->incrementVersion();
            $this->em->flush();
        }

        return $this->redirectToRoute('admin_checklist_etapes', ['id' => $template->getId()]);
    }

    /**
     * Descendre une phase.
     */
    #[Route('/{id}/phase/{phaseId}/descendre', name: 'admin_checklist_phase_down', methods: ['POST'])]
    public function descendrePhase(ChecklistTemplate $template, string $phaseId): Response
    {
        $etapes = $template->getEtapes();
        $phases = $etapes['phases'] ?? [];

        $index = null;
        foreach ($phases as $i => $phase) {
            if ($phase['id'] === $phaseId) {
                $index = $i;
                break;
            }
        }

        if (null !== $index && $index < count($phases) - 1) {
            [$phases[$index], $phases[$index + 1]] = [$phases[$index + 1], $phases[$index]];
            foreach ($phases as $i => &$phase) {
                $phase['ordre'] = $i + 1;
            }
            $etapes['phases'] = $phases;
            $template->setEtapes($etapes);
            $template->incrementVersion();
            $this->em->flush();
        }

        return $this->redirectToRoute('admin_checklist_etapes', ['id' => $template->getId()]);
    }

    // ========== GESTION DES ETAPES ==========

    /**
     * Ajouter une etape a une phase.
     */
    #[Route('/{id}/phase/{phaseId}/etape/ajouter', name: 'admin_checklist_etape_add', methods: ['POST'])]
    public function ajouterEtape(ChecklistTemplate $template, string $phaseId, Request $request): Response
    {
        $etapes = $template->getEtapes();

        foreach ($etapes['phases'] as &$phase) {
            if ($phase['id'] === $phaseId) {
                $phaseEtapes = $phase['etapes'] ?? [];

                // Generer un ID unique
                $maxId = 0;
                foreach ($phaseEtapes as $etape) {
                    $num = (int) str_replace('etape-', '', str_replace($phaseId . '-', '', $etape['id'] ?? '0'));
                    $maxId = max($maxId, $num);
                }
                $newId = $phaseId . '-etape-' . ($maxId + 1);

                $documentId = $request->request->get('documentId');

                $champCible = trim($request->request->get('champCible', ''));

                $phaseEtapes[] = [
                    'id' => $newId,
                    'titre' => $request->request->get('titre', 'Nouvelle etape'),
                    'description' => $request->request->get('description') ?: null,
                    'ordre' => count($phaseEtapes) + 1,
                    'obligatoire' => (bool) $request->request->get('obligatoire', true),
                    'documentId' => $documentId ? (int) $documentId : null,
                    'champCible' => $champCible !== '' ? $champCible : null,
                ];

                $phase['etapes'] = $phaseEtapes;
                break;
            }
        }

        $template->setEtapes($etapes);
        $template->incrementVersion();
        $this->em->flush();

        $this->addFlash('success', 'Etape ajoutee');

        return $this->redirectToRoute('admin_checklist_etapes', ['id' => $template->getId()]);
    }

    /**
     * Modifier une etape.
     */
    #[Route('/{id}/phase/{phaseId}/etape/{etapeId}/modifier', name: 'admin_checklist_etape_edit', methods: ['POST'])]
    public function modifierEtape(
        ChecklistTemplate $template,
        string $phaseId,
        string $etapeId,
        Request $request
    ): Response {
        $etapes = $template->getEtapes();

        foreach ($etapes['phases'] as &$phase) {
            if ($phase['id'] === $phaseId) {
                foreach ($phase['etapes'] as &$etape) {
                    if ($etape['id'] === $etapeId) {
                        $etape['titre'] = $request->request->get('titre', $etape['titre']);
                        $etape['description'] = $request->request->get('description') ?: null;
                        $etape['obligatoire'] = (bool) $request->request->get('obligatoire', false);
                        $documentId = $request->request->get('documentId');
                        $etape['documentId'] = $documentId ? (int) $documentId : null;
                        $champCible = trim($request->request->get('champCible', ''));
                        $etape['champCible'] = $champCible !== '' ? $champCible : null;
                        break 2;
                    }
                }
            }
        }

        $template->setEtapes($etapes);
        $template->incrementVersion();
        $this->em->flush();

        $this->addFlash('success', 'Etape modifiee');

        return $this->redirectToRoute('admin_checklist_etapes', ['id' => $template->getId()]);
    }

    /**
     * Supprimer une etape.
     */
    #[Route('/{id}/phase/{phaseId}/etape/{etapeId}/supprimer', name: 'admin_checklist_etape_delete', methods: ['POST'])]
    public function supprimerEtape(ChecklistTemplate $template, string $phaseId, string $etapeId): Response
    {
        $etapes = $template->getEtapes();

        foreach ($etapes['phases'] as &$phase) {
            if ($phase['id'] === $phaseId) {
                $phase['etapes'] = array_values(array_filter(
                    $phase['etapes'] ?? [],
                    fn ($e) => $e['id'] !== $etapeId
                ));

                // Reindexer les ordres
                foreach ($phase['etapes'] as $i => &$etape) {
                    $etape['ordre'] = $i + 1;
                }
                break;
            }
        }

        $template->setEtapes($etapes);
        $template->incrementVersion();
        $this->em->flush();

        $this->addFlash('success', 'Etape supprimee');

        return $this->redirectToRoute('admin_checklist_etapes', ['id' => $template->getId()]);
    }

    /**
     * Monter une etape.
     */
    #[Route('/{id}/phase/{phaseId}/etape/{etapeId}/monter', name: 'admin_checklist_etape_up', methods: ['POST'])]
    public function monterEtape(ChecklistTemplate $template, string $phaseId, string $etapeId): Response
    {
        $etapes = $template->getEtapes();

        foreach ($etapes['phases'] as &$phase) {
            if ($phase['id'] === $phaseId) {
                $phaseEtapes = $phase['etapes'] ?? [];
                $index = null;

                foreach ($phaseEtapes as $i => $etape) {
                    if ($etape['id'] === $etapeId) {
                        $index = $i;
                        break;
                    }
                }

                if (null !== $index && $index > 0) {
                    [$phaseEtapes[$index - 1], $phaseEtapes[$index]] = [$phaseEtapes[$index], $phaseEtapes[$index - 1]];
                    foreach ($phaseEtapes as $i => &$etape) {
                        $etape['ordre'] = $i + 1;
                    }
                    $phase['etapes'] = $phaseEtapes;
                }
                break;
            }
        }

        $template->setEtapes($etapes);
        $template->incrementVersion();
        $this->em->flush();

        return $this->redirectToRoute('admin_checklist_etapes', ['id' => $template->getId()]);
    }

    /**
     * Descendre une etape.
     */
    #[Route('/{id}/phase/{phaseId}/etape/{etapeId}/descendre', name: 'admin_checklist_etape_down', methods: ['POST'])]
    public function descendreEtape(ChecklistTemplate $template, string $phaseId, string $etapeId): Response
    {
        $etapes = $template->getEtapes();

        foreach ($etapes['phases'] as &$phase) {
            if ($phase['id'] === $phaseId) {
                $phaseEtapes = $phase['etapes'] ?? [];
                $index = null;

                foreach ($phaseEtapes as $i => $etape) {
                    if ($etape['id'] === $etapeId) {
                        $index = $i;
                        break;
                    }
                }

                if (null !== $index && $index < count($phaseEtapes) - 1) {
                    [$phaseEtapes[$index], $phaseEtapes[$index + 1]] = [$phaseEtapes[$index + 1], $phaseEtapes[$index]];
                    foreach ($phaseEtapes as $i => &$etape) {
                        $etape['ordre'] = $i + 1;
                    }
                    $phase['etapes'] = $phaseEtapes;
                }
                break;
            }
        }

        $template->setEtapes($etapes);
        $template->incrementVersion();
        $this->em->flush();

        return $this->redirectToRoute('admin_checklist_etapes', ['id' => $template->getId()]);
    }
}
