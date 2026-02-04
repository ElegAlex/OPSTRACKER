<?php

namespace App\Form;

use App\Entity\Creneau;
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

/**
 * Formulaire pour la creation/edition d'un creneau.
 *
 * Champs : date, heureDebut, heureFin, capacite, lieu, segment (optionnel)
 *
 * @extends AbstractType<Creneau>
 */
class CreneauType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $campagne = $options['campagne'];

        $builder
            ->add('date', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('heureDebut', TimeType::class, [
                'label' => 'Heure de debut',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('heureFin', TimeType::class, [
                'label' => 'Heure de fin',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('capacite', IntegerType::class, [
                'label' => 'Capacite',
                'attr' => [
                    'min' => 1,
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                    'placeholder' => 'Nombre de places',
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
            'data_class' => Creneau::class,
            'campagne' => null,
        ]);

        $resolver->setRequired(['campagne']);
    }
}
