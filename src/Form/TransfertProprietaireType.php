<?php

namespace App\Form;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Formulaire de transfert de propriete d'une campagne.
 * US-210 / RG-111 : Definir le proprietaire d'une campagne
 */
class TransfertProprietaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentProprietaire = $options['current_proprietaire'];

        $builder
            ->add('nouveauProprietaire', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => fn(Utilisateur $u) => sprintf('%s %s (%s)', $u->getPrenom(), $u->getNom(), $u->getEmail()),
                'query_builder' => fn(UtilisateurRepository $repo) => $repo->createQueryBuilder('u')
                    ->andWhere('u.actif = :actif')
                    ->andWhere('u.roles LIKE :roleGest OR u.roles LIKE :roleAdmin')
                    ->andWhere('u.id != :currentId')
                    ->setParameter('actif', true)
                    ->setParameter('roleGest', '%"' . Utilisateur::ROLE_GESTIONNAIRE . '"%')
                    ->setParameter('roleAdmin', '%"' . Utilisateur::ROLE_ADMIN . '"%')
                    ->setParameter('currentId', $currentProprietaire?->getId() ?? 0)
                    ->orderBy('u.nom', 'ASC')
                    ->addOrderBy('u.prenom', 'ASC'),
                'label' => 'Nouveau proprietaire',
                'placeholder' => 'Selectionnez un gestionnaire...',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none',
                ],
                'constraints' => [
                    new NotNull(message: 'Veuillez selectionner un nouveau proprietaire.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'current_proprietaire' => null,
        ]);
        $resolver->setAllowedTypes('current_proprietaire', ['null', Utilisateur::class]);
    }
}
