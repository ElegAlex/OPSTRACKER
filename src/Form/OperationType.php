<?php

namespace App\Form;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\Segment;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire ajout operation manuelle.
 * RG-014 : Statut initial = "A planifier"
 * RG-015 : Donnees personnalisees JSONB
 * RG-018 : 1 technicien assigne maximum
 */
class OperationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Campagne|null $campagne */
        $campagne = $options['campagne'];

        $builder
            ->add('matricule', TextType::class, [
                'label' => 'Matricule',
                'attr' => [
                    'placeholder' => 'Ex: MAT-001',
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom / Libelle',
                'attr' => [
                    'placeholder' => 'Ex: Poste de Jean Dupont',
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
            ])
            ->add('datePlanifiee', DateType::class, [
                'label' => 'Date planifiee',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Notes ou commentaires...',
                    'rows' => 3,
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white resize-none',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
            ])
            ->add('technicienAssigne', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function (Utilisateur $utilisateur) {
                    return $utilisateur->getPrenom() . ' ' . $utilisateur->getNom();
                },
                'label' => 'Technicien assigne',
                'placeholder' => 'Non assigne',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.actif = :actif')
                        ->andWhere('u.roles LIKE :role')
                        ->setParameter('actif', true)
                        ->setParameter('role', '%ROLE_TECHNICIEN%')
                        ->orderBy('u.nom', 'ASC');
                },
            ]);

        // Ajouter le champ segment si la campagne a des segments
        if ($campagne && $campagne->getSegments()->count() > 0) {
            $builder->add('segment', EntityType::class, [
                'class' => Segment::class,
                'choice_label' => 'nom',
                'label' => 'Segment',
                'placeholder' => 'Aucun segment',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
                'choices' => $campagne->getSegments(),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Operation::class,
            'campagne' => null,
        ]);

        $resolver->setAllowedTypes('campagne', ['null', Campagne::class]);
    }
}
