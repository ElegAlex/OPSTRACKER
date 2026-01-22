# OpsTracker Design Reference Package

> Ce package contient tout le nécessaire pour que Claude Code implémente le design OpsTracker de manière cohérente.

## Structure

```
design-reference/
├── DESIGN_SYSTEM.md              # Tokens, règles, composants documentés
├── README.md                     # Ce fichier
├── mockups/
│   ├── portfolio.html            # Vue portefeuille campagnes (static)
│   └── campaign-dashboard.html   # Dashboard campagne (static)
└── twig-components/
    ├── _card-kpi.html.twig       # Widget KPI (4 statuts)
    ├── _status-badge.html.twig   # Badge de statut
    ├── _segment-row.html.twig    # Ligne progression segment
    ├── _campaign-card.html.twig  # Card campagne (portfolio)
    ├── _sidebar.html.twig        # Navigation sidebar
    └── _activity-item.html.twig  # Item flux d'activité
```

---

## Utilisation avec Claude Code

### Prompt Template Recommandé

```markdown
## Contexte Design

Référence design OpsTracker :
- Design System : `/docs/design-reference/DESIGN_SYSTEM.md`
- Mockups HTML : `/docs/design-reference/mockups/`
- Composants Twig : `/docs/design-reference/twig-components/`

### Règles obligatoires

1. **Style Bauhaus** : Pas de border-radius (sauf cercles purs), bordures 2px, géométrie forte
2. **Palette** : ink (#0a0a0a), paper (#f5f5f0), primary (#2563eb), success (#059669), warning (#d97706), danger (#dc2626)
3. **Typo** : Space Grotesk, chiffres en tabular-nums
4. **RGAA** : Triple signaling obligatoire (icône + couleur + texte) pour tous les statuts
5. **Composants** : Réutiliser les composants Twig existants quand possible

### Tâche

[Votre instruction ici]
```

---

## Checklist Implémentation

### Pour chaque nouvelle vue

- [ ] Layout : `flex h-screen` avec sidebar `w-80` + main `flex-1`
- [ ] Header : `border-b-4 border-ink` avec titre + actions
- [ ] Cards : `border-2 border-ink` + liseré couleur `h-2` en haut
- [ ] Boutons : Carrés sans border-radius
- [ ] Icônes : Feather Icons via `data-feather`
- [ ] Chiffres : Classe `.num` (tabular-nums tracking-tight)

### Pour chaque statut affiché

- [ ] Icône présente (signal 1)
- [ ] Couleur appliquée (signal 2)
- [ ] Texte lisible (signal 3)
- [ ] Contraste ≥ 4.5:1 vérifié

---

## Mapping Statuts → Couleurs

### Campagne

| Statut | Couleur | Classe Tailwind |
|--------|---------|-----------------|
| En cours | Vert | `bg-success` |
| À venir | Bleu | `bg-primary` |
| Préparation | Orange | `bg-warning` |
| Terminée | Teal | `bg-complete` |
| Archivée | Gris | `bg-ink/20` |

### Opération

| Statut | Couleur | Classe Tailwind |
|--------|---------|-----------------|
| À planifier | Gris | `bg-muted` |
| Planifié | Bleu | `bg-primary` |
| En cours | Bleu | `bg-primary` |
| Réalisé | Vert | `bg-success` |
| Reporté | Orange | `bg-warning` |
| À remédier | Rouge | `bg-danger` |

---

## Assets à inclure

```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}OpsTracker{% endblock %}</title>
    
    {# Tailwind (dev) ou CSS compilé (prod) #}
    {% block stylesheets %}
        {{ encore_entry_link_tags('app') }}
    {% endblock %}
    
    {# Font #}
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Space Grotesk', system-ui, sans-serif; }
        .num { font-variant-numeric: tabular-nums; letter-spacing: -0.03em; }
    </style>
</head>
<body class="h-full bg-paper text-ink antialiased">
    {% block body %}{% endblock %}
    
    {# Feather Icons #}
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => feather.replace());
        document.addEventListener('turbo:render', () => feather.replace());
    </script>
    
    {% block javascripts %}
        {{ encore_entry_script_tags('app') }}
    {% endblock %}
</body>
</html>
```

---

## Exemples d'utilisation des composants

### KPI Card

```twig
{% include 'components/_card-kpi.html.twig' with {
    count: 619,
    label: 'Réalisé',
    color: 'success',
    icon: 'check-circle',
    percentage: 73,
    trend: '+12 aujourd\'hui'
} %}
```

### Status Badge

```twig
{% include 'components/_status-badge.html.twig' with {
    status: campaign.status,
    size: 'md',
    showIcon: true
} %}
```

### Segment Row

```twig
{% for segment in segments %}
    {% include 'components/_segment-row.html.twig' with { segment: segment } %}
{% endfor %}
```

---

## Notes pour Claude Code

1. **Toujours lire DESIGN_SYSTEM.md** avant de créer une nouvelle vue
2. **Consulter les mockups** pour voir le rendu attendu
3. **Réutiliser les composants Twig** au lieu de recréer
4. **Tester l'accessibilité** : contraste, navigation clavier, labels
5. **Pas d'invention** : suivre strictement le design system établi
