<?php

namespace App\Form;

use App\Entity\Prerequis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire pour creer/modifier un prerequis.
 *
 * RG-090 : Prerequis avec libelle, responsable, date cible (optionnelle)
 *
 * @extends AbstractType<Prerequis>
 */
class PrerequisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libelle',
                'attr' => [
                    'placeholder' => 'Ex: Commander le materiel',
                    'class' => 'w-full px-4 py-3 border-2 border-ink focus:outline-none focus:ring-2 focus:ring-primary',
                ],
            ])
            ->add('responsable', TextType::class, [
                'label' => 'Responsable',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Jean Dupont',
                    'class' => 'w-full px-4 py-3 border-2 border-ink focus:outline-none focus:ring-2 focus:ring-primary',
                ],
            ])
            ->add('dateCible', DateType::class, [
                'label' => 'Date cible',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink focus:outline-none focus:ring-2 focus:ring-primary',
                ],
            ]);

        // Ajouter le statut seulement en mode edition
        if ($options['include_statut']) {
            $builder->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_flip(Prerequis::STATUTS),
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink focus:outline-none focus:ring-2 focus:ring-primary',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prerequis::class,
            'include_statut' => false,
        ]);
    }
}
