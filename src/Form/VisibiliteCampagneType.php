<?php

namespace App\Form;

use App\Entity\Campagne;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de configuration de la visibilite d'une campagne.
 * US-211 / RG-112 : Configurer la visibilite d'une campagne
 *
 * @extends AbstractType<Campagne>
 */
class VisibiliteCampagneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('visibilite', ChoiceType::class, [
                'choices' => array_flip(Campagne::VISIBILITES),
                'label' => 'Mode de visibilite',
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-3',
                ],
            ])
            ->add('utilisateursHabilites', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => fn(Utilisateur $u) => sprintf('%s %s (%s)', $u->getPrenom(), $u->getNom(), $u->getEmail()),
                'query_builder' => fn(UtilisateurRepository $repo) => $repo->createQueryBuilder('u')
                    ->andWhere('u.actif = :actif')
                    ->setParameter('actif', true)
                    ->orderBy('u.nom', 'ASC')
                    ->addOrderBy('u.prenom', 'ASC'),
                'label' => 'Utilisateurs habilites',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none',
                    'size' => 8,
                ],
                'help' => 'Maintenez Ctrl (ou Cmd) pour selectionner plusieurs utilisateurs',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Campagne::class,
        ]);
    }
}
