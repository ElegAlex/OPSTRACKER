<?php

namespace App\Controller\Admin;

use App\Entity\TypeOperation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

/**
 * CRUD EasyAdmin pour TypeOperation.
 * T-305 / US-801 : Creer un type d'operation
 * RG-060 : Type = Nom + Description + Icone + Couleur
 */
class TypeOperationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TypeOperation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Type d\'operation')
            ->setEntityLabelInPlural('Types d\'operation')
            ->setSearchFields(['nom', 'description'])
            ->setDefaultSort(['nom' => 'ASC'])
            ->setPageTitle('index', 'Gestion des types d\'operation')
            ->setPageTitle('new', 'Creer un type d\'operation')
            ->setPageTitle('edit', fn (TypeOperation $t) => sprintf('Modifier "%s"', $t->getNom()))
            ->setPageTitle('detail', fn (TypeOperation $t) => $t->getNom())
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
                return $action->setLabel('Nouveau type');
            })
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('nom', 'Nom')
            ->setColumns(6)
            ->setHelp('RG-060: Nom unique du type d\'operation');

        yield ChoiceField::new('icone', 'Icone')
            ->setChoices(array_flip(TypeOperation::ICONES))
            ->setColumns(3)
            ->setHelp('Icone Lucide affichee dans l\'interface');

        yield ChoiceField::new('couleur', 'Couleur')
            ->setChoices(array_flip(TypeOperation::COULEURS))
            ->setColumns(3)
            ->renderAsBadges([
                'primary' => 'primary',
                'success' => 'success',
                'warning' => 'warning',
                'danger' => 'danger',
                'complete' => 'info',
                'muted' => 'secondary',
            ])
            ->setHelp('Couleur du design system Bauhaus');

        yield TextareaField::new('description', 'Description')
            ->hideOnIndex()
            ->setColumns(12);

        yield BooleanField::new('actif', 'Actif')
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);

        yield DateTimeField::new('createdAt', 'Cree le')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('updatedAt', 'Modifie le')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
