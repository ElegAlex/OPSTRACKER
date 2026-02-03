<?php

namespace App\Controller\Admin;

use App\Entity\Campagne;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

/**
 * CRUD EasyAdmin pour Campagne.
 * T-306 : CRUD Campagne EasyAdmin
 * RG-010 : 5 statuts campagne
 * RG-011 : Nom + Dates obligatoires
 */
class CampagneCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Campagne::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Campagne')
            ->setEntityLabelInPlural('Campagnes')
            ->setSearchFields(['nom', 'description'])
            ->setDefaultSort(['dateDebut' => 'DESC'])
            ->setPageTitle('index', 'Gestion des campagnes')
            ->setPageTitle('new', 'Creer une campagne')
            ->setPageTitle('edit', fn (Campagne $c) => sprintf('Modifier "%s"', $c->getNom()))
            ->setPageTitle('detail', fn (Campagne $c) => $c->getNom())
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('statut')
                ->setLabel('Statut')
                ->setChoices(array_flip(Campagne::STATUTS)))
            ->add(EntityFilter::new('typeOperation')->setLabel('Type d\'operation'))
            ->add(EntityFilter::new('proprietaire')->setLabel('Proprietaire'))
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewFrontend = Action::new('viewFrontend', 'Voir sur le site')
            ->linkToRoute('app_dashboard_campagne', fn (Campagne $c) => ['id' => $c->getId()])
            ->setIcon('fa fa-eye');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $viewFrontend)
            ->add(Crud::PAGE_DETAIL, $viewFrontend)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Nouvelle campagne');
            })
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('nom', 'Nom')
            ->setColumns(8)
            ->setHelp('RG-011: Nom obligatoire');

        yield ChoiceField::new('statut', 'Statut')
            ->setChoices(array_flip(Campagne::STATUTS))
            ->renderAsBadges([
                Campagne::STATUT_PREPARATION => 'warning',
                Campagne::STATUT_A_VENIR => 'primary',
                Campagne::STATUT_EN_COURS => 'success',
                Campagne::STATUT_TERMINEE => 'info',
                Campagne::STATUT_ARCHIVEE => 'secondary',
            ])
            ->setHelp('RG-010: 5 statuts avec workflow');

        yield DateField::new('dateDebut', 'Date debut')
            ->setColumns(4)
            ->setHelp('RG-011: Date debut obligatoire');

        yield DateField::new('dateFin', 'Date fin')
            ->setColumns(4)
            ->setHelp('RG-011: Date fin obligatoire');

        yield TextareaField::new('description', 'Description')
            ->hideOnIndex()
            ->setColumns(12);

        yield AssociationField::new('typeOperation', 'Type d\'operation')
            ->setColumns(6)
            ->setHelp('RG-014: Type d\'operation associe');

        yield AssociationField::new('checklistTemplate', 'Template checklist')
            ->hideOnIndex()
            ->setColumns(6)
            ->setHelp('RG-030: Template de checklist');

        yield AssociationField::new('proprietaire', 'Proprietaire')
            ->hideOnIndex()
            ->setColumns(6);

        yield CollectionField::new('champs', 'Colonnes / Champs')
            ->useEntryCrudForm(CampagneChampCrudController::class)
            ->setEntryIsComplex(true)
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false)
            ->onlyOnForms()
            ->setColumns(12)
            ->setHelp('Definissez les colonnes/champs de la campagne');

        yield IntegerField::new('nombreOperations', 'Operations')
            ->hideOnForm()
            ->formatValue(fn ($value) => $value . ' ops');

        // Reservation publique (type Doodle)
        yield FormField::addPanel('Reservation en ligne')
            ->setIcon('fa fa-calendar-check')
            ->collapsible();

        yield BooleanField::new('reservationOuverte', 'Activer reservation publique')
            ->setHelp('Permet aux agents de reserver via un lien public (type Doodle)')
            ->renderAsSwitch(true)
            ->onlyOnForms();

        yield ChoiceField::new('reservationMode', 'Mode d\'identification')
            ->setChoices([
                'Saisie libre (ouvert a tous)' => Campagne::RESERVATION_MODE_LIBRE,
                'Liste importee (CSV specifique)' => Campagne::RESERVATION_MODE_IMPORT,
                'Annuaire agents (avec filtres)' => Campagne::RESERVATION_MODE_ANNUAIRE,
            ])
            ->onlyOnForms()
            ->setHelp('Libre = saisie texte. Import = CSV specifique. Annuaire = filtrage agents.');

        // Colonne segment : dropdown dynamique basé sur les champs de la campagne
        $colonneChoices = ['' => '-- Aucun (pas de segmentation) --'];
        if ($pageName === Crud::PAGE_EDIT) {
            $campagne = $this->getContext()?->getEntity()?->getInstance();
            if ($campagne instanceof Campagne) {
                foreach ($campagne->getChamps() as $champ) {
                    $colonneChoices[$champ->getNom()] = $champ->getNom();
                }
            }
        }

        yield ChoiceField::new('colonneSegment', 'Colonne segment')
            ->setChoices($colonneChoices)
            ->onlyOnForms()
            ->setHelp('Colonne utilisee pour segmenter les operations. Disponible apres import CSV.');

        yield ChoiceField::new('colonneDatePlanifiee', 'Colonne date planifiee')
            ->setChoices($colonneChoices)
            ->onlyOnForms()
            ->setHelp('Colonne CSV contenant la date de planification des operations.');

        yield ChoiceField::new('colonneHoraire', 'Colonne horaire')
            ->setChoices($colonneChoices)
            ->onlyOnForms()
            ->setHelp('Colonne CSV contenant l\'heure (optionnel, combine avec la date).');

        yield TextField::new('shareToken', 'Token de partage')
            ->onlyOnForms()
            ->setFormTypeOption('disabled', true)
            ->setHelp('Genere automatiquement via la fonction Partage');

        // Suivi du temps d'intervention
        yield FormField::addPanel('Suivi du temps')
            ->setIcon('fa fa-clock')
            ->collapsible();

        yield BooleanField::new('saisieTempsActivee', 'Saisie temps intervention')
            ->setHelp('Activer la saisie obligatoire du temps par les techniciens')
            ->renderAsSwitch(true);

        yield DateTimeField::new('createdAt', 'Cree le')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('updatedAt', 'Modifie le')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
