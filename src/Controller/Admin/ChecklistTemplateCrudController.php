<?php

namespace App\Controller\Admin;

use App\Entity\ChecklistTemplate;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

/**
 * CRUD EasyAdmin pour ChecklistTemplate.
 * T-602 : CRUD Templates EasyAdmin
 * RG-030 : Template = Nom + Version + Etapes ordonnees + Phases optionnelles
 */
class ChecklistTemplateCrudController extends AbstractCrudController
{
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
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Nouveau template');
            })
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
            ->formatValue(fn ($value) => $value . ' etapes');

        yield IntegerField::new('nombreEtapesObligatoires', 'Obligatoires')
            ->hideOnForm()
            ->hideOnIndex();

        // Affichage des phases/etapes en detail uniquement
        if ($pageName === Crud::PAGE_DETAIL) {
            yield TextareaField::new('etapesFormatted', 'Structure des etapes')
                ->hideOnForm()
                ->setTemplatePath('admin/field/checklist_etapes.html.twig');
        }

        yield IntegerField::new('campagnes.count', 'Campagnes')
            ->hideOnForm()
            ->hideOnDetail()
            ->formatValue(fn ($value) => $value . ' campagnes');

        yield IntegerField::new('instances.count', 'Instances')
            ->hideOnForm()
            ->hideOnDetail()
            ->formatValue(fn ($value) => $value . ' instances');

        yield DateTimeField::new('createdAt', 'Cree le')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('updatedAt', 'Modifie le')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
