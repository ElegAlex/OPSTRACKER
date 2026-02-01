# Guide Utilisateur OpsTracker V1

> **Version** : 1.0.0
> **Derniere mise a jour** : 2026-01-22

---

## Introduction

OpsTracker est une application de gestion d'operations IT terrain pour les organisations. Elle permet de piloter des campagnes de migration/deploiement avec un suivi en temps reel.

### Utilisateurs

| Role | Persona | Acces |
|------|---------|-------|
| Gestionnaire IT | Sophie | Dashboard, campagnes, configuration |
| Technicien IT | Karim | Interface terrain mobile, checklists |
| Administrateur | Admin | Configuration globale, utilisateurs |

---

# Guide Sophie - Gestionnaire IT

## Connexion

1. Acceder a l'application : `https://opstracker.cpam.fr`
2. Saisir votre email et mot de passe
3. Cliquer sur "Se connecter"

> **Note** : Apres 5 tentatives echouees, votre compte sera verrouille. Contactez l'administrateur.

---

## Dashboard Global

### Acces
Menu > **Dashboard**

### Elements affiches

#### Widgets KPI
- **Realise** (vert) : Operations terminees avec succes
- **Planifie** (bleu) : Operations programmees
- **Reporte** (orange) : Operations decalees
- **A remedier** (rouge) : Operations en echec

#### Liste des campagnes actives
- Progression en pourcentage
- Nombre d'operations par statut
- Acces rapide au detail

### Actions disponibles
- Filtrer par statut de campagne
- Exporter en PDF
- Partager un lien lecture seule

---

## Gestion des Campagnes

### Creer une campagne

1. Menu > **Campagnes** > **Nouvelle campagne**
2. Remplir les informations :
   - Nom de la campagne
   - Description
   - Dates de debut et fin
   - Type d'operation
3. Cliquer sur **Suivant**
4. Importer les operations :
   - Option 1 : Upload fichier CSV
   - Option 2 : Ajout manuel
5. Configurer le mapping des colonnes (si CSV)
6. Associer un template de checklist
7. Valider la creation

### Structure du fichier CSV

```csv
matricule;nom;segment;email
PC-001;Poste Dupont;Batiment A;dupont@cpam.fr
PC-002;Poste Martin;Batiment B;martin@cpam.fr
```

| Colonne | Obligatoire | Description |
|---------|-------------|-------------|
| matricule | Oui | Identifiant unique |
| nom | Oui | Nom de l'operation |
| segment | Non | Groupe/batiment |
| email | Non | Contact agent |

### Modifier une campagne

1. Ouvrir la campagne
2. Cliquer sur l'icone **Editer**
3. Modifier les informations
4. Sauvegarder

### Archiver une campagne

1. Ouvrir la campagne
2. Menu Actions > **Archiver**
3. Confirmer

> Les campagnes archivees restent accessibles en lecture seule.

---

## Suivi des Operations

### Vue tableau

1. Ouvrir une campagne
2. Onglet **Operations**

#### Colonnes affichees
- Matricule
- Nom
- Statut (badge colore)
- Segment
- Technicien assigne
- Date planifiee

#### Filtres disponibles
- Par statut
- Par segment
- Par technicien
- Recherche textuelle

### Modifier une operation

1. Cliquer sur la ligne
2. Modifier les champs :
   - Statut (menu deroulant)
   - Technicien assigne
   - Date planifiee
   - Notes
3. Sauvegarder

### Exporter les operations

1. Cliquer sur **Exporter CSV**
2. Selectionner les colonnes souhaitees
3. Appliquer les filtres si necessaire
4. Telecharger

---

## Dashboard Campagne

### Acces
Cliquer sur une campagne > **Dashboard**

### Widgets

#### KPI principaux
- Total operations
- Progression globale
- Realise aujourd'hui
- Alertes (reporte + a remedier)

#### Progression par segment
- Barre de progression
- Pourcentage realise
- Indicateur de retard (si < 50% et beaucoup de problemes)

#### Statistiques equipe
- Nombre de techniciens actifs
- Operations par technicien
- Taux de realisation moyen

### Actions

#### Exporter en PDF
1. Cliquer sur **PDF**
2. Le fichier se telecharge automatiquement
3. Format A4 paysage avec tous les KPI

#### Partager un lien
1. Cliquer sur **Partager**
2. Copier le lien genere
3. Ce lien permet la consultation sans connexion

> **Securite** : Le lien ne permet aucune action, uniquement la visualisation.

---

## Prerequis

### Prerequis globaux
Conditions a verifier avant le debut de la campagne.

1. Onglet **Prerequis**
2. Cliquer sur **Ajouter prerequis global**
3. Remplir :
   - Titre
   - Description
   - Statut (A faire / En cours / Fait)
4. Sauvegarder

### Prerequis par segment
Conditions specifiques a un batiment/groupe.

1. Onglet **Prerequis**
2. Selectionner le segment
3. Cliquer sur **Ajouter prerequis segment**
4. Remplir les informations
5. Sauvegarder

> Les prerequis sont **declaratifs** et **non bloquants**.

---

## Templates de Checklist

### Creer un template

1. Menu > **Configuration** > **Templates checklist**
2. Cliquer sur **Nouveau template**
3. Definir :
   - Nom du template
   - Description
   - Phases (optionnel)
   - Etapes avec leur ordre
4. Sauvegarder

### Structure d'une etape

| Champ | Description |
|-------|-------------|
| Titre | Nom de l'etape |
| Description | Details/instructions |
| Obligatoire | Oui/Non |
| Document lie | PDF/guide associe |

### Versionning

Chaque modification cree une nouvelle version. Les instances existantes conservent la version au moment de la creation.

---

## Historique (Audit)

### Consulter l'historique

1. Ouvrir une campagne
2. Menu Actions > **Historique**

### Informations tracees
- Qui a fait la modification
- Quand
- Quels champs ont change
- Anciennes et nouvelles valeurs

---

# Guide Karim - Technicien Terrain

## Connexion Mobile

1. Scanner le QR code ou acceder a l'URL
2. Saisir email et mot de passe
3. L'interface s'adapte automatiquement au mobile

---

## Mes Interventions

### Acces
L'ecran principal affiche automatiquement vos interventions du jour.

### Filtres rapides
- **Aujourd'hui** : Interventions planifiees ce jour
- **Cette semaine** : Planning hebdomadaire
- **Toutes** : Liste complete

### Informations affichees
- Matricule
- Nom du poste
- Segment/Batiment
- Statut actuel (badge)
- Heure planifiee

---

## Detail d'une Intervention

### Acces
Taper sur une intervention dans la liste.

### Informations
- Coordonnees de l'agent
- Adresse/localisation
- Notes specifiques
- Historique des changements

### Changer le statut

Les boutons sont grands (56px minimum) pour une utilisation tactile.

| Bouton | Action |
|--------|--------|
| **Planifie** (bleu) | Confirmer le RDV |
| **En cours** (bleu) | Demarrer l'intervention |
| **Realise** (vert) | Terminer avec succes |
| **Reporte** (orange) | Reporter (motif requis) |
| **A remedier** (rouge) | Signaler un probleme |

> Apres un changement de statut, vous revenez automatiquement a la liste.

---

## Checklist

### Acces
Detail intervention > **Checklist**

### Utilisation

1. Lire l'etape
2. Effectuer l'action
3. Cocher la case (bouton 48x48px)
4. Passer a l'etape suivante

### Phases

Si le template contient des phases :
- Les etapes sont groupees
- Une phase doit etre completee avant de passer a la suivante
- Indicateur de progression par phase

### Documents

Certaines etapes peuvent avoir des documents lies :
- Cliquer sur l'icone document
- Le PDF s'ouvre dans une nouvelle fenetre
- Scripts telechargeables

---

## Mode Hors-Ligne

> **Fonctionnalite V2** : En cours de developpement

Actuellement, une connexion internet est requise pour utiliser l'application.

---

## Problemes Frequents

### "Je ne vois pas mes interventions"

1. Verifier que vous etes bien connecte
2. Verifier le filtre de date (defaut: aujourd'hui)
3. Contacter Sophie pour verifier l'assignation

### "Je ne peux pas changer le statut"

1. Verifier que vous etes bien le technicien assigne
2. Verifier que la campagne est active (pas archivee)
3. Certaines transitions sont impossibles (ex: Realise → A planifier)

### "La checklist ne s'affiche pas"

1. Un template doit etre associe a la campagne
2. L'instance est creee au premier acces
3. Contacter Sophie si le probleme persiste

---

# Annexes

## Statuts des Operations

| Statut | Couleur | Icone | Description |
|--------|---------|-------|-------------|
| A planifier | Gris | Calendrier | Pas encore programme |
| Planifie | Bleu | Horloge | RDV fixe |
| En cours | Bleu | Play | Intervention demarree |
| Realise | Vert | Check | Termine avec succes |
| Reporte | Orange | Pause | Decale (motif requis) |
| A remedier | Rouge | Alerte | Probleme a resoudre |

## Raccourcis Clavier (Desktop)

| Raccourci | Action |
|-----------|--------|
| `Ctrl + F` | Recherche |
| `Escape` | Fermer modal |
| `Enter` | Valider formulaire |

## Support

- Email : support-opstracker@cpam.fr
- Documentation : `/docs/` dans l'application

---

*Guide utilisateur OpsTracker v1.0.0 - 2026-01-22*
