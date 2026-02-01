<?php

namespace App\Form;

use App\Entity\Campagne;
use App\Entity\ChecklistTemplate;
use App\Entity\TypeOperation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire creation campagne - Etape 4/4 (Workflow & Template).
 * RG-014 : Association TypeOperation + ChecklistTemplate
 */
class CampagneStep4Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeOperation', EntityType::class, [
                'class' => TypeOperation::class,
                'choice_label' => 'nom',
                'label' => 'Type d\'operation',
                'placeholder' => 'Selectionnez un type...',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('t')
                        ->where('t.actif = :actif')
                        ->setParameter('actif', true)
                        ->orderBy('t.nom', 'ASC');
                },
            ])
            ->add('checklistTemplate', EntityType::class, [
                'class' => ChecklistTemplate::class,
                'choice_label' => 'nom',
                'label' => 'Template de checklist',
                'placeholder' => 'Selectionnez un template...',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('t')
                        ->where('t.actif = :actif')
                        ->setParameter('actif', true)
                        ->orderBy('t.nom', 'ASC');
                },
            ])
            // Reservation end-user (type Doodle)
            ->add('reservationOuverte', CheckboxType::class, [
                'label' => 'Activer la reservation publique',
                'required' => false,
                'attr' => [
                    'class' => 'w-5 h-5 rounded border-ink/20 text-primary focus:ring-primary',
                ],
                'label_attr' => [
                    'class' => 'font-medium text-ink',
                ],
            ])
            ->add('reservationMode', ChoiceType::class, [
                'label' => 'Mode d\'identification',
                'choices' => [
                    'Saisie libre (ouvert a tous)' => Campagne::RESERVATION_MODE_LIBRE,
                    'Liste importee (CSV specifique)' => Campagne::RESERVATION_MODE_IMPORT,
                    'Annuaire agents (avec filtres)' => Campagne::RESERVATION_MODE_ANNUAIRE,
                ],
                'required' => false,
                'placeholder' => 'Selectionnez un mode...',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Campagne::class,
            'allow_extra_fields' => true,
        ]);
    }
}
