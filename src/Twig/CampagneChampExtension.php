<?php

namespace App\Twig;

use App\Service\CampagneChampService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Extension Twig pour la gestion des champs dynamiques de campagne.
 *
 * Fournit des filtres pour normaliser les noms de champs
 * de maniere coherente avec le code PHP.
 */
class CampagneChampExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('normalize_field_name', [CampagneChampService::class, 'normalizeFieldName']),
        ];
    }
}
