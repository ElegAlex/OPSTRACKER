<?php

namespace App\Form;

use App\Entity\Segment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Segment>
 */
class SegmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du segment',
                'attr' => [
                    'placeholder' => 'Ex: Batiment A, Service RH, Etage 1...',
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none text-sm',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
            ->add('couleur', ChoiceType::class, [
                'label' => 'Couleur',
                'choices' => array_flip(Segment::COULEURS),
                'expanded' => true,
                'multiple' => false,
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-ink mb-2',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Segment::class,
        ]);
    }
}
