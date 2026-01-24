<?php

namespace App\Form;

use App\Entity\Segment;
use App\Repository\SegmentRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire pour la generation automatique de creneaux.
 *
 * Champs : date_debut, date_fin, heure_debut, heure_fin, duree_minutes, capacite, lieu, segment
 *
 * RG-130 : Generation automatique
 * - Skip weekends (samedi=6, dimanche=7)
 * - Skip pause dejeuner (12h-14h)
 */
class CreneauGenerationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $campagne = $options['campagne'];

        $builder
            ->add('date_debut', DateType::class, [
                'label' => 'Date de debut',
                'widget' => 'single_text',
                'data' => $campagne->getDateDebut(),
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de debut est obligatoire.'),
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('date_fin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'data' => $campagne->getDateFin(),
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de fin est obligatoire.'),
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('heure_debut', TimeType::class, [
                'label' => 'Heure de debut',
                'widget' => 'single_text',
                'data' => new \DateTime('09:00'),
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'heure de debut est obligatoire.'),
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('heure_fin', TimeType::class, [
                'label' => 'Heure de fin',
                'widget' => 'single_text',
                'data' => new \DateTime('17:00'),
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'heure de fin est obligatoire.'),
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('duree_minutes', IntegerType::class, [
                'label' => 'Duree d\'un creneau (minutes)',
                'data' => 30,
                'constraints' => [
                    new Assert\NotBlank(message: 'La duree est obligatoire.'),
                    new Assert\Positive(message: 'La duree doit etre positive.'),
                    new Assert\Range(min: 5, max: 480, notInRangeMessage: 'La duree doit etre entre 5 et 480 minutes.'),
                ],
                'attr' => [
                    'min' => 5,
                    'max' => 480,
                    'step' => 5,
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                    'placeholder' => 'Ex: 30',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('capacite', IntegerType::class, [
                'label' => 'Capacite par creneau',
                'data' => 1,
                'constraints' => [
                    new Assert\NotBlank(message: 'La capacite est obligatoire.'),
                    new Assert\Positive(message: 'La capacite doit etre positive.'),
                ],
                'attr' => [
                    'min' => 1,
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                    'placeholder' => 'Nombre de places par creneau',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                    'placeholder' => 'Ex: Salle A, Bureau 201...',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('segment', EntityType::class, [
                'label' => 'Segment (optionnel)',
                'class' => Segment::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => 'Tous les segments',
                'query_builder' => function (SegmentRepository $repository) use ($campagne) {
                    return $repository->createQueryBuilder('s')
                        ->where('s.campagne = :campagne')
                        ->setParameter('campagne', $campagne)
                        ->orderBy('s.ordre', 'ASC');
                },
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'campagne' => null,
        ]);

        $resolver->setRequired(['campagne']);
    }
}
