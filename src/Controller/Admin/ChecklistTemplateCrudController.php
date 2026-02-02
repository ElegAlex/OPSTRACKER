<?php

namespace App\Controller\Admin;

use App\Entity\ChecklistTemplate;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD EasyAdmin pour ChecklistTemplate.
 * T-602 : CRUD Templates EasyAdmin
 * RG-030 : Template = Nom + Version + Etapes ordonnees + Phases optionnelles
 *
 * Inclut les actions custom pour gerer phases et etapes (ex-ChecklistEtapesController).
 */
class ChecklistTemplateCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentRepository $documentRepo,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ChecklistTemplate::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Template de checklist')
            ->setEntityLabelInPlural('Templates de checklist')
            ->setSearchFields(['nom', 'description'])
            ->setDefaultSort(['nom' => 'ASC', 'version' => 'DESC'])
            ->setPageTitle('index', 'Templates de checklist')
            ->setPageTitle('new', 'Creer un template')
            ->setPageTitle('edit', fn (ChecklistTemplate $t) => sprintf('Modifier "%s" (v%d)', $t->getNom(), $t->getVersion()))
            ->setPageTitle('detail', fn (ChecklistTemplate $t) => sprintf('%s (v%d)', $t->getNom(), $t->getVersion()))
            ->setHelp('index', 'RG-030 : Les templates definissent les etapes que les techniciens devront suivre lors des interventions.')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('actif')->setLabel('Actif'))
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // Action custom pour gerer les etapes (maintenant une action CRUD)
        $gererEtapes = Action::new('gererEtapes', 'Gerer les etapes', 'fa fa-list-check')
            ->linkToCrudAction('gererEtapes')
            ->displayIf(static fn (ChecklistTemplate $entity): bool => null !== $entity->getId());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $gererEtapes)
            ->add(Crud::PAGE_DETAIL, $gererEtapes)
            ->add(Crud::PAGE_EDIT, $gererEtapes)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Nouveau template');
            })
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'gererEtapes', Action::EDIT, Action::DELETE])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('nom', 'Nom')
            ->setColumns(8)
            ->setHelp('Nom descriptif du template');

        yield IntegerField::new('version', 'Version')
            ->hideOnForm()
            ->formatValue(fn ($value) => 'v' . $value);

        yield BooleanField::new('actif', 'Actif')
            ->setColumns(4)
            ->setHelp('Seuls les templates actifs peuvent etre associes aux nouvelles campagnes')
            ->renderAsSwitch();

        yield TextareaField::new('description', 'Description')
            ->hideOnIndex()
            ->setColumns(12)
            ->setHelp('Description du template et de son usage');

        yield IntegerField::new('nombreEtapes', 'Nb etapes')
            ->hideOnForm()
            ->formatValue(fn ($value) => $value . ' etape(s)');

        yield IntegerField::new('nombreEtapesObligatoires', 'Obligatoires')
            ->hideOnForm()
            ->hideOnIndex();

        // Affichage des phases/etapes en detail (lecture)
        if ($pageName === Crud::PAGE_DETAIL) {
            yield TextareaField::new('etapes', 'Structure des etapes')
                ->setTemplatePath('admin/field/checklist_etapes.html.twig');
        }

        // Note : l'edition des etapes se fait via l'action "Gerer les etapes"

        yield IntegerField::new('campagnes.count', 'Campagnes')
            ->hideOnForm()
            ->hideOnDetail()
            ->formatValue(fn ($value) => $value . ' campagne(s)');

        yield IntegerField::new('instances.count', 'Instances')
            ->hideOnForm()
            ->hideOnDetail()
            ->formatValue(fn ($value) => $value . ' instance(s)');

        yield DateTimeField::new('createdAt', 'Cree le')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('updatedAt', 'Modifie le')
            ->hideOnForm()
            ->hideOnIndex();
    }

    // =========================================================================
    // ACTIONS CUSTOM — Gestion des phases et etapes
    // =========================================================================

    /**
     * Page principale : affiche phases et etapes du template.
     */
    public function gererEtapes(AdminContext $context): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();

        $etapes = $template->getEtapes();
        $phases = $etapes['phases'] ?? [];

        return $this->render('admin/checklist/etapes.html.twig', [
            'template' => $template,
            'phases' => $phases,
            'documents' => $this->documentRepo->findBy([], ['nomOriginal' => 'ASC']),
        ]);
    }

    // ========== GESTION DES PHASES ==========

    /**
     * Ajouter une phase.
     */
    public function ajouterPhase(AdminContext $context, Request $request): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();

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

        return $this->redirectToGererEtapes($template);
    }

    /**
     * Modifier une phase.
     */
    public function modifierPhase(AdminContext $context, Request $request): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();
        $phaseId = $request->request->get('phaseId');

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

        return $this->redirectToGererEtapes($template);
    }

    /**
     * Supprimer une phase (et ses etapes).
     */
    public function supprimerPhase(AdminContext $context, Request $request): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();
        $phaseId = $request->query->get('phaseId');

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

        return $this->redirectToGererEtapes($template);
    }

    /**
     * Monter une phase.
     */
    public function monterPhase(AdminContext $context, Request $request): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();
        $phaseId = $request->query->get('phaseId');

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

        return $this->redirectToGererEtapes($template);
    }

    /**
     * Descendre une phase.
     */
    public function descendrePhase(AdminContext $context, Request $request): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();
        $phaseId = $request->query->get('phaseId');

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

        return $this->redirectToGererEtapes($template);
    }

    // ========== GESTION DES ETAPES ==========

    /**
     * Ajouter une etape a une phase.
     */
    public function ajouterEtape(AdminContext $context, Request $request): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();
        $phaseId = $request->request->get('phaseId');

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

        return $this->redirectToGererEtapes($template);
    }

    /**
     * Modifier une etape.
     */
    public function modifierEtape(AdminContext $context, Request $request): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();
        $phaseId = $request->request->get('phaseId');
        $etapeId = $request->request->get('etapeId');

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

        return $this->redirectToGererEtapes($template);
    }

    /**
     * Supprimer une etape.
     */
    public function supprimerEtape(AdminContext $context, Request $request): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();
        $phaseId = $request->query->get('phaseId');
        $etapeId = $request->query->get('etapeId');

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

        return $this->redirectToGererEtapes($template);
    }

    /**
     * Monter une etape.
     */
    public function monterEtape(AdminContext $context, Request $request): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();
        $phaseId = $request->query->get('phaseId');
        $etapeId = $request->query->get('etapeId');

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

        return $this->redirectToGererEtapes($template);
    }

    /**
     * Descendre une etape.
     */
    public function descendreEtape(AdminContext $context, Request $request): Response
    {
        /** @var ChecklistTemplate $template */
        $template = $context->getEntity()->getInstance();
        $phaseId = $request->query->get('phaseId');
        $etapeId = $request->query->get('etapeId');

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

        return $this->redirectToGererEtapes($template);
    }

    // =========================================================================
    // HELPER
    // =========================================================================

    /**
     * Redirection vers la page gererEtapes.
     */
    private function redirectToGererEtapes(ChecklistTemplate $template): Response
    {
        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction('gererEtapes')
                ->setEntityId($template->getId())
                ->generateUrl()
        );
    }
}
