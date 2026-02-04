<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * Formulaire de creation de campagne - Etape 2/4 (Upload CSV ou creation manuelle).
 *
 * Regles metier :
 * - RG-012 : Max 100 000 lignes, encodage auto-detecte
 * - RG-013 : Fichier .csv uniquement accepte
 * - Creation manuelle : definir les colonnes sans import CSV
 *
 * @extends AbstractType<null>
 */
class CampagneStep2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('csvFile', FileType::class, [
                'label' => 'Fichier CSV',
                'help' => 'Formats acceptes : .csv (separateur auto-detecte). Maximum 100 000 lignes.',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '50M',
                        'maxSizeMessage' => 'Le fichier est trop volumineux ({{ size }} {{ suffix }}). Maximum autorise : {{ limit }} {{ suffix }}.',
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                        ],
                        'mimeTypesMessage' => 'Veuillez telecharger un fichier CSV valide.',
                    ]),
                ],
                'attr' => [
                    'accept' => '.csv',
                    'class' => 'block w-full text-sm file:mr-4 file:py-3 file:px-6 file:border-0 file:text-sm file:font-semibold file:bg-ink file:text-white hover:file:bg-ink/90',
                ],
            ])
            ->add('colonnes_manuelles', TextareaType::class, [
                'label' => 'Definir les colonnes manuellement',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => "Une colonne par ligne :\nMatricule\nNom\nBureau\nEtage",
                    'rows' => 6,
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white font-mono text-sm',
                ],
                'help' => 'Si vous n\'importez pas de CSV, definissez ici les colonnes de votre campagne (une par ligne).',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
