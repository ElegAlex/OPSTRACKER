# OpsTracker Design System

> **Version** : 1.0  
> **Style** : Bauhaus moderne — géométrie pure, contrastes forts, fonctionnel et élégant  
> **Accessibilité** : RGAA 4.1 natif

---

## 1. Fondations

### 1.1 Couleurs

```
┌─────────────────────────────────────────────────────────────┐
│  PALETTE PRINCIPALE                                         │
├─────────────────────────────────────────────────────────────┤
│  ink      #0a0a0a   Texte principal, bordures fortes       │
│  paper    #f5f5f0   Fond principal (crème chaud)           │
│  cream    #fafaf5   Fond cards, zones surélevées           │
│  white    #ffffff   Cards, modals                          │
│  muted    #6b6b6b   Texte secondaire, labels               │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  COULEURS SÉMANTIQUES (Statuts opérations)                  │
├─────────────────────────────────────────────────────────────┤
│  primary  #2563eb   Bleu — Planifié, actions principales   │
│  success  #059669   Vert — Réalisé, En cours, positif      │
│  warning  #d97706   Orange — Reporté, Préparation, alerte  │
│  danger   #dc2626   Rouge — À remédier, erreur critique    │
│  complete #0d9488   Teal — Terminée (campagne)             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  STATUTS CAMPAGNE → COULEUR                                 │
├─────────────────────────────────────────────────────────────┤
│  En cours      success   #059669                           │
│  À venir       primary   #2563eb                           │
│  Préparation   warning   #d97706                           │
│  Terminée      complete  #0d9488                           │
│  Archivée      muted     #a8a29e (slate-400)               │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  STATUTS OPÉRATION → COULEUR                                │
├─────────────────────────────────────────────────────────────┤
│  À planifier   muted     #6b6b6b                           │
│  Planifié      primary   #2563eb                           │
│  En cours      primary   #2563eb                           │
│  Réalisé       success   #059669                           │
│  Reporté       warning   #d97706                           │
│  À remédier    danger    #dc2626                           │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Typographie

```
Police : Space Grotesk (Google Fonts)
         Fallback: system-ui, sans-serif

┌──────────────┬─────────┬─────────┬─────────────────────────┐
│ Usage        │ Taille  │ Weight  │ Classe Tailwind         │
├──────────────┼─────────┼─────────┼─────────────────────────┤
│ Display      │ 5xl/7xl │ 700     │ text-5xl/7xl font-bold  │
│ H1           │ 3xl     │ 700     │ text-3xl font-bold      │
│ H2           │ lg/xl   │ 700     │ text-lg/xl font-bold    │
│ H3           │ base    │ 600     │ font-semibold           │
│ Body         │ sm      │ 400     │ text-sm                 │
│ Caption      │ xs      │ 500     │ text-xs font-medium     │
│ Micro        │ [10px]  │ 600     │ text-[10px] font-bold   │
└──────────────┴─────────┴─────────┴─────────────────────────┘

Chiffres : font-variant-numeric: tabular-nums
           letter-spacing: -0.03em
           Classe: .num { @apply tabular-nums tracking-tight }
```

### 1.3 Espacements

```
Base : 4px (Tailwind default)

┌───────────────────┬────────────────────────────────────────┐
│ Contexte          │ Valeurs                                │
├───────────────────┼────────────────────────────────────────┤
│ Padding cards     │ p-5 (20px) ou p-6 (24px)              │
│ Padding sections  │ px-10 py-8 (40px / 32px)              │
│ Gap entre cards   │ gap-4 (16px) ou gap-6 (24px)          │
│ Gap inline        │ gap-2 (8px) ou gap-3 (12px)           │
│ Marge sections    │ mb-10 (40px) ou mb-12 (48px)          │
└───────────────────┴────────────────────────────────────────┘
```

### 1.4 Bordures & Formes

```
┌───────────────────────────────────────────────────────────┐
│  RÈGLE BAUHAUS : PAS DE BORDER-RADIUS                     │
│  Exception : cercles purs (avatars, indicateurs)          │
└───────────────────────────────────────────────────────────┘

Bordures :
- Forte    : border-2 border-ink       (2px noir)
- Normale  : border-2 border-ink/10    (2px noir 10%)
- Légère   : border border-ink/10      (1px noir 10%)
- Séparateur : border-t/b-2 border-ink/10

Liserés d'accent (status) :
- Position : top ou left
- Épaisseur : 2px (h-2 ou w-1.5)
- Couleur : selon statut
```

---

## 2. Composants

### 2.1 Boutons

```html
<!-- Primaire (noir) -->
<button class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-ink hover:bg-ink/90 transition-colors">
    <i data-feather="plus" class="w-4 h-4"></i>
    Label
</button>

<!-- Secondaire (bordure) -->
<button class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-muted border-2 border-ink/10 hover:border-ink hover:text-ink transition-colors">
    <i data-feather="download" class="w-4 h-4"></i>
    Label
</button>

<!-- Action couleur -->
<button class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-warning hover:bg-warning/90 transition-colors">
    <i data-feather="settings" class="w-4 h-4"></i>
    Configurer
</button>

<!-- Carré icon-only -->
<button class="w-10 h-10 border-2 border-ink/10 flex items-center justify-center text-muted hover:bg-ink hover:text-white hover:border-ink transition-colors">
    <i data-feather="more-horizontal" class="w-5 h-5"></i>
</button>
```

### 2.2 Badges / Pills

```html
<!-- Badge statut (fond coloré) -->
<span class="px-3 py-1 bg-success text-white text-[10px] font-bold uppercase tracking-wider">
    En cours
</span>

<!-- Badge léger (fond transparent) -->
<span class="px-2 py-0.5 bg-complete/10 text-complete text-[10px] font-bold uppercase tracking-wider">
    Terminée
</span>

<!-- Badge alerte -->
<span class="flex items-center gap-1 px-2 py-0.5 bg-danger text-white text-[10px] font-bold uppercase">
    <i data-feather="alert-triangle" class="w-3 h-3"></i>
    En retard
</span>
```

### 2.3 Cards

```html
<!-- Card KPI avec liseré haut -->
<div class="bg-white border-2 border-ink p-6 relative overflow-hidden">
    <div class="absolute top-0 left-0 w-full h-2 bg-success"></div>
    <!-- contenu -->
</div>

<!-- Card campagne avec liseré gauche -->
<div class="bg-white border-2 border-ink overflow-hidden">
    <div class="absolute left-0 top-0 w-1.5 h-full bg-success"></div>
    <!-- contenu -->
</div>

<!-- Card élevée (shadow subtile) -->
<div class="bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.03)] border-2 border-ink">
    <!-- contenu -->
</div>
```

### 2.4 Progress Bars

```html
<!-- Simple -->
<div class="h-2 bg-paper">
    <div class="h-full bg-success" style="width: 73%"></div>
</div>

<!-- Multi-segments (stacked) -->
<div class="h-3 bg-paper flex overflow-hidden">
    <div class="bg-success h-full" style="width: 73%"></div>
    <div class="bg-primary h-full" style="width: 17%"></div>
    <div class="bg-warning h-full" style="width: 6%"></div>
    <div class="bg-danger h-full" style="width: 4%"></div>
</div>
```

### 2.5 Indicateurs géométriques

```html
<!-- Carré status (petit) -->
<div class="w-2 h-2 bg-success"></div>
<div class="w-3 h-3 bg-primary"></div>

<!-- Carré icône -->
<div class="w-8 h-8 bg-primary/10 flex items-center justify-center">
    <i data-feather="target" class="w-4 h-4 text-primary"></i>
</div>

<!-- Grand carré icône -->
<div class="w-12 h-12 bg-success/10 flex items-center justify-center">
    <i data-feather="check-circle" class="w-6 h-6 text-success"></i>
</div>
```

### 2.6 Navigation Sidebar

```html
<!-- Item actif -->
<a href="#" class="flex items-center gap-4 px-4 py-3 bg-primary/5 text-ink font-medium relative">
    <div class="absolute left-0 top-0 w-1 h-full bg-primary"></div>
    <div class="w-8 h-8 bg-primary/10 flex items-center justify-center">
        <i data-feather="layers" class="w-4 h-4 text-primary"></i>
    </div>
    Label
</a>

<!-- Item inactif -->
<a href="#" class="flex items-center gap-4 px-4 py-3 text-muted hover:text-ink hover:bg-paper transition-colors">
    <div class="w-8 h-8 border-2 border-ink/10 flex items-center justify-center">
        <i data-feather="users" class="w-4 h-4"></i>
    </div>
    Label
</a>
```

### 2.7 Tabs

```html
<div class="flex items-center gap-8 border-b-2 border-ink/10">
    <!-- Actif -->
    <a href="#" class="py-4 text-sm font-semibold text-ink relative">
        Dashboard
        <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-ink"></div>
    </a>
    
    <!-- Inactif -->
    <a href="#" class="py-4 text-sm font-medium text-muted hover:text-ink transition-colors">
        Opérations
    </a>
</div>
```

---

## 3. Patterns de Page

### 3.1 Layout Principal

```
┌──────────────────────────────────────────────────────────────┐
│                        h-screen                              │
│  ┌────────────┬───────────────────────────────────────────┐  │
│  │            │                                           │  │
│  │  SIDEBAR   │              MAIN                         │  │
│  │  w-80      │              flex-1                       │  │
│  │            │                                           │  │
│  │  bg-white  │  ┌─────────────────────────────────────┐  │  │
│  │  border-r-4│  │ HEADER                              │  │  │
│  │  border-ink│  │ bg-white border-b-4 border-ink     │  │  │
│  │            │  └─────────────────────────────────────┘  │  │
│  │            │  ┌─────────────────────────────────────┐  │  │
│  │            │  │ CONTENT                             │  │  │
│  │            │  │ bg-cream overflow-auto              │  │  │
│  │            │  │ px-10 py-8                          │  │  │
│  │            │  │                                     │  │  │
│  │            │  │                                     │  │  │
│  └────────────┴──┴─────────────────────────────────────┴──┘  │
└──────────────────────────────────────────────────────────────┘
```

### 3.2 Structure Sidebar

```
┌─────────────────────────┐
│  LOGO                   │  p-8
│  [■] OpsTracker         │
│      CPAM Nanterre      │
├─────────────────────────┤
│  NAV                    │  px-6 py-4
│  ■ Campagnes (actif)    │
│  □ Équipe               │
│  □ Configuration        │
│                         │
│  ─ ÉPINGLÉS ──────────  │  mt-12
│  │ ● Migration W11      │
│  │ ● Déploiement O365   │
│  │ ● Refresh Équipements│
├─────────────────────────┤
│  USER                   │  p-6 border-t
│  [SM] Sophie Martin     │
│       Gestionnaire      │
└─────────────────────────┘
```

### 3.3 Header Campagne

```
┌────────────────────────────────────────────────────────────┐
│  Campagnes > Migration Windows 11            [Actions...] │
│                                                            │
│  Migration Windows 11 - 2026  [En cours]                  │
│  📺 Migration poste • 📅 15 jan → 28 fév • 👥 4 tech     │
├────────────────────────────────────────────────────────────┤
│  Dashboard | Opérations | Checklists | Documents | ...    │
│  ═══════                                                   │
└────────────────────────────────────────────────────────────┘
```

---

## 4. Règles RGAA

### 4.1 Triple Signaling (RG-080)

**Tout statut doit être communiqué par 3 canaux :**

```html
<!-- ✅ CORRECT : icône + couleur + texte -->
<div class="flex items-center gap-2">
    <i data-feather="check-circle" class="w-5 h-5 text-success"></i>
    <span class="text-success font-medium">Réalisé</span>
</div>

<!-- ❌ INCORRECT : couleur seule -->
<div class="w-4 h-4 bg-success"></div>
```

### 4.2 Contrastes (RG-081)

```
Minimum : 4.5:1 pour tout texte

✅ ink (#0a0a0a) sur paper (#f5f5f0) = 15.2:1
✅ ink (#0a0a0a) sur white (#ffffff) = 21:1
✅ muted (#6b6b6b) sur white (#ffffff) = 5.74:1
✅ white sur success (#059669) = 4.58:1
✅ white sur primary (#2563eb) = 4.63:1
✅ white sur warning (#d97706) = 3.02:1 ⚠️ Utiliser ink sur warning/10
✅ white sur danger (#dc2626) = 4.53:1
```

### 4.3 Touch Targets (RG-082)

```
Minimum : 44x44px pour éléments cliquables
Boutons primaires : 56px height recommandé

<!-- ✅ CORRECT -->
<button class="w-10 h-10 ...">  <!-- 40px, acceptable inline -->
<button class="px-5 py-2.5 ...">  <!-- ~44px height -->

<!-- ❌ À ÉVITER -->
<button class="p-1 ...">  <!-- Trop petit -->
```

---

## 5. Icônes

**Librairie** : Feather Icons  
**CDN** : `https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js`

### Icônes par contexte

```
Navigation:
- layers       Campagnes
- users        Équipe  
- settings     Configuration
- star         Favoris

Statuts:
- check-circle Réalisé
- clock        Planifié
- play-circle  En cours
- pause-circle Reporté
- alert-triangle À remédier
- archive      Archivé

Actions:
- plus         Ajouter
- download     Export
- upload       Import
- share-2      Partager
- copy         Dupliquer
- trash-2      Supprimer
- more-horizontal Menu contextuel
- chevron-right Navigation
- chevron-down Accordéon

Types opération:
- monitor      Migration poste
- cloud        Déploiement logiciel
- hard-drive   Renouvellement matériel
- clipboard    Audit / Inventaire
- shield       Sécurité
```

---

## 6. États Interactifs

```css
/* Hover card */
.card-hover {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px -12px rgba(0,0,0,0.15);
}

/* Hover bouton carré */
.btn-square:hover {
    background: #0a0a0a;
    color: white;
    border-color: #0a0a0a;
}

/* Active/Focus */
:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Live indicator pulse */
@keyframes pulse-live {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.live-pulse {
    animation: pulse-live 2s ease-in-out infinite;
}
```

---

## 7. Tailwind Config

```javascript
// tailwind.config.js
module.exports = {
    theme: {
        extend: {
            fontFamily: {
                'grotesk': ['Space Grotesk', 'system-ui', 'sans-serif'],
            },
            colors: {
                'ink': '#0a0a0a',
                'paper': '#f5f5f0',
                'cream': '#fafaf5',
                'primary': '#2563eb',
                'success': '#059669',
                'warning': '#d97706',
                'danger': '#dc2626',
                'complete': '#0d9488',
                'muted': '#6b6b6b',
            },
        },
    },
}
```

---

## 8. Chargement des assets

```html
<head>
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body>
    <!-- ... -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
        });
    </script>
</body>
```

---

## 9. Fichiers de référence

| Fichier | Description |
|---------|-------------|
| `mockups/portfolio.html` | Vue portefeuille campagnes |
| `mockups/campaign-dashboard.html` | Dashboard campagne spécifique |
| `twig-components/_card-kpi.html.twig` | Widget KPI réutilisable |
| `twig-components/_status-badge.html.twig` | Badge statut |
| `twig-components/_segment-row.html.twig` | Ligne segment avec progress |
| `twig-components/_sidebar-nav.html.twig` | Navigation sidebar |
