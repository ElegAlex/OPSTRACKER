<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire de changement de mot de passe (T-1004, RG-001)
 */
class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('current_password', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'attr' => [
                    'autocomplete' => 'current-password',
                    'class' => 'w-full px-4 py-3 border-2 border-ink focus:outline-none focus:ring-2 focus:ring-primary',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le mot de passe actuel est obligatoire.']),
                ],
            ])
            ->add('new_password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'w-full px-4 py-3 border-2 border-ink focus:outline-none focus:ring-2 focus:ring-primary',
                    ],
                    'help' => 'RG-001 : Min 8 caracteres, 1 majuscule, 1 chiffre, 1 caractere special',
                ],
                'second_options' => [
                    'label' => 'Confirmer le nouveau mot de passe',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'w-full px-4 py-3 border-2 border-ink focus:outline-none focus:ring-2 focus:ring-primary',
                    ],
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nouveau mot de passe est obligatoire.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caracteres.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
