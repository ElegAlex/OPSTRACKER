<?php

namespace App\Service;

/**
 * Service utilitaire pour la gestion des champs dynamiques de campagne.
 *
 * Fournit une normalisation coherente des noms de champs entre PHP et Twig.
 */
class CampagneChampService
{
    /**
     * Noms des champs systeme (non personnalisables).
     * Ces champs sont geres par l'application et non par les CampagneChamp.
     *
     * @var array<string>
     */
    private const NATIVE_FIELDS = [
        'identifiant',
        'description',
        'statut',
        'segment',
        'technicien',
        'date_planifiee',
        'date_realisation',
        'notes',
    ];

    /**
     * Verifie si un nom de champ correspond a un champ systeme natif.
     *
     * @param string $champNom Le nom du champ a verifier
     * @return bool True si c'est un champ natif
     */
    public static function isNativeField(string $champNom): bool
    {
        return in_array(strtolower($champNom), self::NATIVE_FIELDS, true);
    }

    /**
     * Normalise un nom de champ pour creer un identifiant de formulaire valide.
     *
     * Transforme les caracteres accentues et speciaux en equivalents ASCII,
     * puis remplace tout caractere non-alphanumerique par un underscore.
     *
     * Exemples:
     * - "Telephone" -> "champ_Telephone"
     * - "N Bureau" -> "champ_N__Bureau"
     * - "Code-Postal" -> "champ_Code_Postal"
     *
     * @param string $champNom Le nom du champ a normaliser
     * @return string L'identifiant normalise avec prefixe "champ_"
     */
    public static function normalizeFieldName(string $champNom): string
    {
        // Translitteration des accents (ex: e -> e, c -> c)
        if (function_exists('transliterator_transliterate')) {
            $normalized = transliterator_transliterate('Any-Latin; Latin-ASCII', $champNom);
        } else {
            // Fallback si intl n'est pas disponible
            $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $champNom);
        }

        // Remplacer tous les caracteres non-alphanumeriques par underscore
        $normalized = preg_replace('/[^a-zA-Z0-9_]/', '_', $normalized);

        return 'champ_' . $normalized;
    }
}
