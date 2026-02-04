<?php

namespace App\Service;

use App\Entity\Operation;
use App\Entity\TypeOperation;

/**
 * Service de gestion des champs personnalises.
 *
 * Regles metier implementees :
 * - RG-015 : Champs JSONB stockes en PostgreSQL avec index GIN
 * - RG-061 : 5 types de champs : Texte court (255), Texte long, Nombre, Date, Liste
 */
class ChampPersonnaliseService
{
    // Types de champs disponibles (RG-061)
    public const TYPE_TEXTE_COURT = 'texte_court';
    public const TYPE_TEXTE_LONG = 'texte_long';
    public const TYPE_NOMBRE = 'nombre';
    public const TYPE_DATE = 'date';
    public const TYPE_LISTE = 'liste';

    public const TYPES = [
        self::TYPE_TEXTE_COURT => 'Texte court (255 car.)',
        self::TYPE_TEXTE_LONG => 'Texte long',
        self::TYPE_NOMBRE => 'Nombre',
        self::TYPE_DATE => 'Date',
        self::TYPE_LISTE => 'Liste déroulante',
    ];

    /**
     * Retourne la definition d'un champ vide.
     *
     * @return array{code: string, label: string, type: string, obligatoire: bool, options: array<int, string>}
     */
    public function creerChampVide(): array
    {
        return [
            'code' => '',
            'label' => '',
            'type' => self::TYPE_TEXTE_COURT,
            'obligatoire' => false,
            'options' => [],
        ];
    }

    /**
     * Valide la structure d'un champ personnalise.
     *
     * @param array<string, mixed> $champ
     * @return array<int, string> Liste des erreurs
     */
    public function validerChamp(array $champ): array
    {
        $erreurs = [];

        // Code obligatoire
        if (empty($champ['code'])) {
            $erreurs[] = 'Le code du champ est obligatoire.';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $champ['code'])) {
            $erreurs[] = 'Le code doit commencer par une lettre minuscule et ne contenir que des lettres, chiffres et underscores.';
        }

        // Label obligatoire
        if (empty($champ['label'])) {
            $erreurs[] = 'Le libellé du champ est obligatoire.';
        }

        // Type valide
        if (!isset(self::TYPES[$champ['type'] ?? ''])) {
            $erreurs[] = 'Type de champ invalide.';
        }

        // Options pour liste
        if (($champ['type'] ?? '') === self::TYPE_LISTE) {
            if (empty($champ['options'])) {
                $erreurs[] = 'Une liste déroulante doit avoir au moins une option.';
            }
        }

        return $erreurs;
    }

    /**
     * Valide tous les champs d'un TypeOperation.
     *
     * @return array<int, string> Liste des erreurs
     */
    public function validerChampsTypeOperation(TypeOperation $typeOperation): array
    {
        $champs = $typeOperation->getChampsPersonnalises() ?? [];
        $erreurs = [];
        $codes = [];

        foreach ($champs as $index => $champ) {
            $champsErreurs = $this->validerChamp($champ);
            foreach ($champsErreurs as $erreur) {
                $erreurs[] = sprintf('Champ #%d : %s', $index + 1, $erreur);
            }

            // Vérifier unicité du code
            $code = $champ['code'] ?? '';
            if ($code && in_array($code, $codes, true)) {
                $erreurs[] = sprintf('Champ #%d : Le code "%s" est déjà utilisé.', $index + 1, $code);
            }
            $codes[] = $code;
        }

        return $erreurs;
    }

    /**
     * Valide une valeur par rapport à un champ.
     *
     * @param array<string, mixed> $champ
     * @return string|null Erreur ou null si valide
     */
    public function validerValeur(array $champ, mixed $valeur): ?string
    {
        $obligatoire = $champ['obligatoire'] ?? false;
        $type = $champ['type'] ?? self::TYPE_TEXTE_COURT;

        // Vérifier si obligatoire
        if ($obligatoire && ($valeur === null || $valeur === '')) {
            return sprintf('Le champ "%s" est obligatoire.', $champ['label']);
        }

        // Si vide et non obligatoire, OK
        if ($valeur === null || $valeur === '') {
            return null;
        }

        switch ($type) {
            case self::TYPE_TEXTE_COURT:
                if (strlen((string) $valeur) > 255) {
                    return sprintf('Le champ "%s" ne doit pas dépasser 255 caractères.', $champ['label']);
                }
                break;

            case self::TYPE_NOMBRE:
                if (!is_numeric($valeur)) {
                    return sprintf('Le champ "%s" doit être un nombre.', $champ['label']);
                }
                break;

            case self::TYPE_DATE:
                if (!$this->estDateValide((string) $valeur)) {
                    return sprintf('Le champ "%s" doit être une date valide (YYYY-MM-DD).', $champ['label']);
                }
                break;

            case self::TYPE_LISTE:
                $options = $champ['options'] ?? [];
                if (!in_array($valeur, $options, true)) {
                    return sprintf('Le champ "%s" doit être une des options disponibles.', $champ['label']);
                }
                break;
        }

        return null;
    }

    /**
     * Valide les données personnalisées d'une opération.
     *
     * @return array<int, string> Liste des erreurs
     */
    public function validerDonneesOperation(Operation $operation, TypeOperation $typeOperation): array
    {
        $champs = $typeOperation->getChampsPersonnalises() ?? [];
        $donnees = $operation->getDonneesPersonnalisees() ?? [];
        $erreurs = [];

        foreach ($champs as $champ) {
            $code = $champ['code'] ?? '';
            $valeur = $donnees[$code] ?? null;
            $erreur = $this->validerValeur($champ, $valeur);
            if ($erreur) {
                $erreurs[] = $erreur;
            }
        }

        return $erreurs;
    }

    /**
     * Génère un formulaire HTML pour un champ.
     *
     * @param array<string, mixed> $champ
     */
    public function genererInputHtml(array $champ, mixed $valeur, string $prefixNom = ''): string
    {
        $type = $champ['type'] ?? self::TYPE_TEXTE_COURT;
        $code = $champ['code'] ?? '';
        $label = $champ['label'] ?? '';
        $obligatoire = $champ['obligatoire'] ?? false;
        $name = $prefixNom ? "{$prefixNom}[{$code}]" : $code;
        $required = $obligatoire ? 'required' : '';

        switch ($type) {
            case self::TYPE_TEXTE_LONG:
                return sprintf(
                    '<textarea name="%s" id="champ_%s" class="form-control" rows="3" %s>%s</textarea>',
                    htmlspecialchars($name),
                    htmlspecialchars($code),
                    $required,
                    htmlspecialchars((string) $valeur)
                );

            case self::TYPE_NOMBRE:
                return sprintf(
                    '<input type="number" step="any" name="%s" id="champ_%s" class="form-control" value="%s" %s>',
                    htmlspecialchars($name),
                    htmlspecialchars($code),
                    htmlspecialchars((string) $valeur),
                    $required
                );

            case self::TYPE_DATE:
                return sprintf(
                    '<input type="date" name="%s" id="champ_%s" class="form-control" value="%s" %s>',
                    htmlspecialchars($name),
                    htmlspecialchars($code),
                    htmlspecialchars((string) $valeur),
                    $required
                );

            case self::TYPE_LISTE:
                $options = $champ['options'] ?? [];
                $html = sprintf(
                    '<select name="%s" id="champ_%s" class="form-select" %s>',
                    htmlspecialchars($name),
                    htmlspecialchars($code),
                    $required
                );
                $html .= '<option value="">-- Sélectionner --</option>';
                foreach ($options as $option) {
                    $selected = $valeur === $option ? 'selected' : '';
                    $html .= sprintf(
                        '<option value="%s" %s>%s</option>',
                        htmlspecialchars($option),
                        $selected,
                        htmlspecialchars($option)
                    );
                }
                $html .= '</select>';
                return $html;

            case self::TYPE_TEXTE_COURT:
            default:
                return sprintf(
                    '<input type="text" name="%s" id="champ_%s" class="form-control" value="%s" maxlength="255" %s>',
                    htmlspecialchars($name),
                    htmlspecialchars($code),
                    htmlspecialchars((string) $valeur),
                    $required
                );
        }
    }

    /**
     * Convertit les options d'une liste (string séparé par virgules) en tableau.
     *
     * @return array<int, string>
     */
    public function parseOptions(string $optionsString): array
    {
        if (empty($optionsString)) {
            return [];
        }

        return array_map('trim', explode(',', $optionsString));
    }

    /**
     * Convertit un tableau d'options en string.
     *
     * @param array<int, string> $options
     */
    public function optionsToString(array $options): string
    {
        return implode(', ', $options);
    }

    /**
     * Vérifie si une chaîne est une date valide.
     */
    private function estDateValide(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
