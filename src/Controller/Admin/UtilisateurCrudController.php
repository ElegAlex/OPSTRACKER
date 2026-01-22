<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

class UtilisateurCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Utilisateur::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setSearchFields(['email', 'nom', 'prenom'])
            ->setDefaultSort(['nom' => 'ASC', 'prenom' => 'ASC'])
            ->setPageTitle('index', 'Gestion des utilisateurs')
            ->setPageTitle('new', 'Creer un utilisateur')
            ->setPageTitle('edit', fn (Utilisateur $u) => sprintf('Modifier %s', $u->getNomComplet()))
            ->setPageTitle('detail', fn (Utilisateur $u) => $u->getNomComplet())
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('actif')->setLabel('Actif'))
            ->add(ChoiceFilter::new('roles')
                ->setLabel('Role')
                ->setChoices([
                    'Admin' => Utilisateur::ROLE_ADMIN,
                    'Gestionnaire' => Utilisateur::ROLE_GESTIONNAIRE,
                    'Technicien' => Utilisateur::ROLE_TECHNICIEN,
                ]))
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Nouvel utilisateur');
            })
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $rolesChoices = [
            'Administrateur' => Utilisateur::ROLE_ADMIN,
            'Gestionnaire' => Utilisateur::ROLE_GESTIONNAIRE,
            'Technicien' => Utilisateur::ROLE_TECHNICIEN,
        ];

        yield IdField::new('id')->hideOnForm();

        yield TextField::new('prenom', 'Prenom')
            ->setColumns(6);

        yield TextField::new('nom', 'Nom')
            ->setColumns(6);

        yield EmailField::new('email', 'Email');

        yield ChoiceField::new('roles', 'Roles')
            ->setChoices($rolesChoices)
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setHelp('RG-003: Admin = tout acces, Gestionnaire = campagnes, Technicien = ses interventions')
            ->hideOnIndex();

        // Afficher les rôles en lecture seule sur l'index
        yield ArrayField::new('roles', 'Roles')
            ->onlyOnIndex()
            ->formatValue(function ($value) {
                $labels = [
                    'ROLE_ADMIN' => 'Admin',
                    'ROLE_GESTIONNAIRE' => 'Gestionnaire',
                    'ROLE_TECHNICIEN' => 'Technicien',
                    'ROLE_USER' => '',
                ];
                $result = [];
                foreach ($value as $role) {
                    if (isset($labels[$role]) && $labels[$role] !== '') {
                        $result[] = $labels[$role];
                    }
                }
                return implode(', ', $result);
            });

        yield TextField::new('plainPassword', 'Mot de passe')
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirmer',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'mapped' => false,
                'required' => $pageName === Crud::PAGE_NEW,
            ])
            ->setHelp('RG-001: Min 8 car, 1 majuscule, 1 chiffre, 1 special')
            ->onlyOnForms();

        yield BooleanField::new('actif', 'Actif')
            ->renderAsSwitch($pageName !== Crud::PAGE_INDEX);

        yield IntegerField::new('failedLoginAttempts', 'Echecs connexion')
            ->hideOnForm()
            ->setHelp('RG-006: Verrouillage apres 5 echecs');

        yield DateTimeField::new('lockedUntil', 'Verrouille jusqu\'a')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Cree le')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('updatedAt', 'Modifie le')
            ->hideOnForm()
            ->hideOnIndex();
    }

    /**
     * @param Utilisateur $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->handlePassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * @param Utilisateur $entityInstance
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->handlePassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function handlePassword(Utilisateur $utilisateur): void
    {
        $form = $this->getContext()?->getRequest()?->get('Utilisateur');
        $plainPassword = $form['plainPassword']['first'] ?? null;

        if ($plainPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($utilisateur, $plainPassword);
            $utilisateur->setPassword($hashedPassword);
        }
    }
}
