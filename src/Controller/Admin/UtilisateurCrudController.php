<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Service\UtilisateurService;
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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD Utilisateurs - Reserve aux administrateurs.
 *
 * @extends AbstractCrudController<Utilisateur>
 */
#[IsGranted('ROLE_ADMIN')]
class UtilisateurCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private Security $security,
        private UtilisateurService $utilisateurService,
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
                    'Coordinateur' => Utilisateur::ROLE_COORDINATEUR,
                ]))
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // Action pour desactiver/activer un utilisateur (T-1002, RG-005)
        $toggleActif = Action::new('toggleActif', 'Activer/Desactiver')
            ->linkToCrudAction('toggleActif')
            ->setIcon('fa fa-toggle-on')
            ->displayIf(fn (Utilisateur $u) => $this->canToggleActif($u));

        // Action pour deverrouiller un compte (RG-006)
        $unlock = Action::new('unlock', 'Deverrouiller')
            ->linkToCrudAction('unlock')
            ->setIcon('fa fa-unlock')
            ->displayIf(fn (Utilisateur $u) => $u->isLocked());

        // Action pour voir les statistiques (T-1003)
        $stats = Action::new('viewStats', 'Statistiques')
            ->linkToCrudAction('viewStats')
            ->setIcon('fa fa-chart-bar');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $toggleActif)
            ->add(Crud::PAGE_INDEX, $unlock)
            ->add(Crud::PAGE_DETAIL, $toggleActif)
            ->add(Crud::PAGE_DETAIL, $unlock)
            ->add(Crud::PAGE_DETAIL, $stats)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Nouvel utilisateur');
            })
        ;
    }

    private function canToggleActif(Utilisateur $utilisateur): bool
    {
        $currentUser = $this->security->getUser();
        // Un admin ne peut pas se desactiver lui-meme
        if ($currentUser instanceof Utilisateur && $currentUser->getId() === $utilisateur->getId()) {
            return false;
        }
        return true;
    }

    public function configureFields(string $pageName): iterable
    {
        $rolesChoices = [
            'Administrateur' => Utilisateur::ROLE_ADMIN,
            'Gestionnaire' => Utilisateur::ROLE_GESTIONNAIRE,
            'Technicien' => Utilisateur::ROLE_TECHNICIEN,
            'Coordinateur (RG-114)' => Utilisateur::ROLE_COORDINATEUR,
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
                    'ROLE_COORDINATEUR' => 'Coordinateur',
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
        // RG-004 : Un admin ne peut pas retrograder son propre role
        $currentUser = $this->security->getUser();
        if ($currentUser instanceof Utilisateur
            && $currentUser->getId() === $entityInstance->getId()
            && $currentUser->isAdmin()
            && !$entityInstance->isAdmin()
        ) {
            $this->addFlash('danger', 'RG-004 : Vous ne pouvez pas retirer votre propre role administrateur.');
            return;
        }

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

    /**
     * T-1002 : Desactiver/Activer un utilisateur (RG-005 : conservation historique)
     */
    public function toggleActif(): Response
    {
        $context = $this->getContext();
        $entityId = $context->getRequest()->query->get('entityId');

        $entityManager = $this->container->get('doctrine')->getManager();
        $utilisateur = $entityManager->getRepository(Utilisateur::class)->find($entityId);

        if (!$utilisateur) {
            throw $this->createNotFoundException('Utilisateur non trouve');
        }

        // RG-004 : Un admin ne peut pas se desactiver lui-meme
        $currentUser = $this->security->getUser();
        if ($currentUser instanceof Utilisateur && $currentUser->getId() === $utilisateur->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas vous desactiver vous-meme.');
            return $this->redirect($context->getReferrer());
        }

        // RG-005 : Conservation historique - on desactive seulement, pas de suppression
        $this->utilisateurService->setActif($utilisateur, !$utilisateur->isActif());

        $status = $utilisateur->isActif() ? 'active' : 'desactive';
        $this->addFlash('success', sprintf('Utilisateur %s %s avec succes.', $utilisateur->getNomComplet(), $status));

        return $this->redirect($context->getReferrer());
    }

    /**
     * Deverrouiller un compte (RG-006)
     */
    public function unlock(): Response
    {
        $context = $this->getContext();
        $entityId = $context->getRequest()->query->get('entityId');

        $entityManager = $this->container->get('doctrine')->getManager();
        $utilisateur = $entityManager->getRepository(Utilisateur::class)->find($entityId);

        if (!$utilisateur) {
            throw $this->createNotFoundException('Utilisateur non trouve');
        }

        $this->utilisateurService->unlock($utilisateur);
        $this->addFlash('success', sprintf('Compte de %s deverrouille avec succes.', $utilisateur->getNomComplet()));

        return $this->redirect($context->getReferrer());
    }

    /**
     * T-1003 : Voir les statistiques utilisateur
     */
    public function viewStats(): Response
    {
        $context = $this->getContext();
        $entityId = $context->getRequest()->query->get('entityId');

        $entityManager = $this->container->get('doctrine')->getManager();
        $utilisateur = $entityManager->getRepository(Utilisateur::class)->find($entityId);

        if (!$utilisateur) {
            throw $this->createNotFoundException('Utilisateur non trouve');
        }

        // Recuperer les statistiques via le service
        $stats = $this->utilisateurService->getStatistiques($utilisateur);

        return $this->render('admin/utilisateur/stats.html.twig', [
            'utilisateur' => $utilisateur,
            'stats' => $stats,
        ]);
    }
}
