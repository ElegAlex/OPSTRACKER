<?php

namespace App\Controller\Admin;

use App\Entity\Agent;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

/**
 * CRUD Agent pour EasyAdmin - Sprint 16 (T-1611)
 *
 * Gestion des agents metier pour le module Reservation V2.
 *
 * Regles metier :
 * - RG-124 : Manager ne voit que les agents de son service (filtre)
 *
 * @extends AbstractCrudController<Agent>
 */
class AgentCrudController extends AbstractCrudController
{

    public static function getEntityFqcn(): string
    {
        return Agent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Agent')
            ->setEntityLabelInPlural('Agents')
            ->setSearchFields(['matricule', 'email', 'nom', 'prenom', 'service', 'site'])
            ->setDefaultSort(['nom' => 'ASC', 'prenom' => 'ASC'])
            ->setPageTitle('index', 'Gestion des agents')
            ->setPageTitle('new', 'Creer un agent')
            ->setPageTitle('edit', fn (Agent $a) => sprintf('Modifier %s', $a->getNomComplet()))
            ->setPageTitle('detail', fn (Agent $a) => $a->getNomComplet())
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('actif')->setLabel('Actif'))
            ->add(TextFilter::new('service')->setLabel('Service'))
            ->add(TextFilter::new('site')->setLabel('Site'))
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $toggleActif = Action::new('toggleActif', 'Activer/Desactiver')
            ->linkToCrudAction('toggleActif')
            ->setIcon('fa fa-toggle-on');

        $importCsv = Action::new('importCsv', 'Importer CSV', 'fa fa-upload')
            ->linkToRoute('admin_agent_import')
            ->setCssClass('btn btn-primary')
            ->createAsGlobalAction();

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $toggleActif)
            ->add(Crud::PAGE_DETAIL, $toggleActif)
            ->add(Crud::PAGE_INDEX, $importCsv)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Nouvel agent');
            })
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('matricule', 'Matricule')
            ->setColumns(4)
            ->setHelp('Identifiant unique de l\'agent (ex: AGT0001)');

        yield TextField::new('prenom', 'Prenom')
            ->setColumns(4);

        yield TextField::new('nom', 'Nom')
            ->setColumns(4);

        yield EmailField::new('email', 'Email');

        yield TextField::new('service', 'Service')
            ->setColumns(6)
            ->setHelp('Ex: Prestations Maladie, Accueil, etc.');

        yield TextField::new('site', 'Site')
            ->setColumns(6)
            ->setHelp('Ex: Nanterre - Siege, Colombes, etc.');

        yield AssociationField::new('manager', 'Manager')
            ->setHelp('RG-124 : Le manager voit uniquement les agents de son service')
            ->setQueryBuilder(function (QueryBuilder $qb) {
                // Filtrer pour ne montrer que les agents qui sont managers (ont des subordonnes)
                // ou qui peuvent etre managers
                return $qb
                    ->andWhere('entity.actif = :actif')
                    ->setParameter('actif', true)
                    ->orderBy('entity.nom', 'ASC')
                    ->addOrderBy('entity.prenom', 'ASC');
            })
            ->hideOnIndex();

        yield TextField::new('manager', 'Manager')
            ->onlyOnIndex()
            ->formatValue(fn ($value, Agent $agent) => $agent->getManager()?->getNomComplet() ?? '-');

        yield BooleanField::new('actif', 'Actif')
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);

        yield DateTimeField::new('createdAt', 'Cree le')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('updatedAt', 'Modifie le')
            ->hideOnForm()
            ->hideOnIndex();
    }

    /**
     * Action pour activer/desactiver un agent
     */
    public function toggleActif(): \Symfony\Component\HttpFoundation\Response
    {
        $context = $this->getContext();
        if ($context === null) {
            throw $this->createNotFoundException('Contexte admin non disponible');
        }

        $entityId = $context->getRequest()->query->get('entityId');

        $entityManager = $this->container->get('doctrine')->getManager();
        $agent = $entityManager->getRepository(Agent::class)->find($entityId);

        if (!$agent) {
            throw $this->createNotFoundException('Agent non trouve');
        }

        $agent->setActif(!$agent->isActif());
        $entityManager->flush();

        $status = $agent->isActif() ? 'active' : 'desactive';
        $this->addFlash('success', sprintf('Agent %s %s avec succes.', $agent->getNomComplet(), $status));

        $referrer = $context->getReferrer();
        if ($referrer === null) {
            return $this->redirectToRoute('admin');
        }

        return $this->redirect($referrer);
    }
}
