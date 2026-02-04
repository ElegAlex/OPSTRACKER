<?php

namespace App\Controller\Admin;

use App\Entity\CampagneChamp;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * CRUD CampagneChamp pour EasyAdmin.
 *
 * Utilise principalement dans le CollectionField de CampagneCrudController
 * pour la gestion inline des champs/colonnes d'une campagne.
 *
 * @extends AbstractCrudController<CampagneChamp>
 */
class CampagneChampCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CampagneChamp::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('nom', 'Nom du champ')
            ->setRequired(true)
            ->setHelp('Nom de la colonne/champ');

        yield IntegerField::new('ordre', 'Ordre')
            ->setRequired(false)
            ->setHelp('Ordre d\'affichage (optionnel)');
    }
}
