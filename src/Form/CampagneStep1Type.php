<?php

namespace App\Form;

use App\Entity\Campagne;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire creation campagne - Etape 1/4 (Infos generales).
 * RG-011 : Nom + Dates (debut/fin) obligatoires
 */
class CampagneStep1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la campagne',
                'attr' => [
                    'placeholder' => 'Ex: Migration Windows 11 - 2026',
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Decrivez les objectifs de cette campagne...',
                    'rows' => 4,
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white resize-none',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de debut',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
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
        ]);
    }
}
