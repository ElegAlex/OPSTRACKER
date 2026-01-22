<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de creation de campagne - Etape 3/4 (Mapping colonnes).
 *
 * Regles metier :
 * - RG-012 : Mapping colonnes CSV vers champs Operation
 * - RG-014 : Statut initial = "A planifier"
 */
class CampagneStep3Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $headers = $options['csv_headers'];
        $suggestedMapping = $options['suggested_mapping'];

        // Creer les choix pour le mapping
        $columnChoices = ['' => null];
        foreach ($headers as $index => $header) {
            $columnChoices[$header] = $index;
        }

        // Champs obligatoires
        $builder->add('mapping_matricule', ChoiceType::class, [
            'label' => 'Matricule *',
            'help' => 'Identifiant unique de l\'opération (obligatoire)',
            'choices' => $columnChoices,
            'data' => $suggestedMapping['matricule'] ?? null,
            'placeholder' => '-- Sélectionner une colonne --',
            'required' => true,
        ]);

        $builder->add('mapping_nom', ChoiceType::class, [
            'label' => 'Nom / Libellé *',
            'help' => 'Nom de l\'opération ou de l\'agent (obligatoire)',
            'choices' => $columnChoices,
            'data' => $suggestedMapping['nom'] ?? null,
            'placeholder' => '-- Sélectionner une colonne --',
            'required' => true,
        ]);

        // Champs optionnels
        $builder->add('mapping_segment', ChoiceType::class, [
            'label' => 'Segment / Bâtiment',
            'help' => 'Groupe logique pour regrouper les opérations (créé automatiquement)',
            'choices' => $columnChoices,
            'data' => $suggestedMapping['segment'] ?? null,
            'placeholder' => '-- Aucun --',
            'required' => false,
        ]);

        $builder->add('mapping_notes', ChoiceType::class, [
            'label' => 'Notes',
            'help' => 'Commentaires ou remarques',
            'choices' => $columnChoices,
            'data' => $suggestedMapping['notes'] ?? null,
            'placeholder' => '-- Aucun --',
            'required' => false,
        ]);

        $builder->add('mapping_date_planifiee', ChoiceType::class, [
            'label' => 'Date planifiée',
            'help' => 'Date prévue pour l\'intervention (formats : JJ/MM/AAAA, AAAA-MM-JJ)',
            'choices' => $columnChoices,
            'data' => $suggestedMapping['date_planifiee'] ?? null,
            'placeholder' => '-- Aucun --',
            'required' => false,
        ]);

        // Champs caches pour les metadonnees
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
            'csv_headers' => [],
            'suggested_mapping' => [],
            'csv_encoding' => 'UTF-8',
            'csv_separator' => ';',
        ]);

        $resolver->setAllowedTypes('csv_headers', 'array');
        $resolver->setAllowedTypes('suggested_mapping', 'array');
        $resolver->setAllowedTypes('csv_encoding', 'string');
        $resolver->setAllowedTypes('csv_separator', 'string');
    }
}
