<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de creation de campagne - Etape 3/4 (Confirmation import).
 *
 * RG-015 : TOUTES les colonnes CSV deviennent des CampagneChamp
 * Plus de mapping manuel - import automatique
 *
 * @extends AbstractType<null>
 */
class CampagneStep3Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Seuls les champs caches pour les metadonnees CSV
        $builder->add('csv_encoding', HiddenType::class, [
            'data' => $options['csv_encoding'],
        ]);

        $builder->add('csv_separator', HiddenType::class, [
            'data' => $options['csv_separator'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csv_encoding' => 'UTF-8',
            'csv_separator' => ';',
        ]);

        $resolver->setAllowedTypes('csv_encoding', 'string');
        $resolver->setAllowedTypes('csv_separator', 'string');
    }
}
