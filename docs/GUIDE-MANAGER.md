# Guide Manager - Positionner vos agents

> **OpsTracker V2** - Module Reservation
> Documentation utilisateur pour les managers CPAM

---

## 1. Acceder a l'interface manager

### Connexion

1. Connectez-vous a **OpsTracker** avec vos identifiants habituels
2. Vous devez avoir le role **Gestionnaire** ou superieur

### Acces aux fonctionnalites

Depuis le tableau de bord d'une campagne :
1. Cliquez sur la campagne concernee
2. Reperer le bouton **"Gerer mon equipe"** (icone equipe)
3. Vous accedez a l'interface de gestion des agents

---

## 2. Voir l'etat de positionnement de mon equipe

### Vue liste des agents

L'interface affiche :

| Colonne | Description |
|---------|-------------|
| Agent | Nom, prenom et matricule |
| Service | Service de rattachement |
| Statut | Positionne ou Non positionne |
| Creneau | Date et heure si positionne |
| Actions | Boutons d'action disponibles |

### Indicateurs globaux

En haut de page :
- **Agents positionnes** : X / Y (ex: 6/10)
- **Taux de positionnement** : pourcentage
- **Alertes** : notifications importantes

### Filtres disponibles

- Par **statut** (positionne / non positionne)
- Par **service** (si multi-services)
- Par **site** (si multi-sites)

---

## 3. Positionner un agent

### Quand positionner ?

Vous pouvez positionner un agent qui :
- N'a **pas encore de reservation** pour cette campagne
- Fait partie de **votre equipe** (RG-124)

### Etapes

1. Identifiez l'agent **non positionne** dans la liste
2. Cliquez sur **"Positionner"**
3. La liste des creneaux disponibles s'affiche
4. Selectionnez le creneau souhaite
5. Confirmez le positionnement

### Apres positionnement

- L'agent recoit un **email de confirmation** avec le fichier ICS
- La tracabilite est enregistree : "Positionne par [votre nom]" (RG-125)
- Le creneau est reserve pour cet agent

### Creneaux non disponibles

Certains creneaux n'apparaissent pas :
- Creneaux **complets**
- Creneaux **verrouilles** (moins de 2 jours avant)

---

## 4. Modifier la reservation d'un agent

### Quand modifier ?

Vous pouvez modifier si :
- Le creneau actuel **n'est pas verrouille**
- D'autres creneaux sont **disponibles**

### Etapes

1. Identifiez l'agent positionne
2. Cliquez sur **"Modifier"**
3. Selectionnez le nouveau creneau
4. Confirmez la modification

### Apres modification

- L'agent recoit un **email de modification** (RG-126) incluant :
  - L'ancien creneau (barre)
  - Le nouveau creneau
  - Un nouveau fichier ICS

---

## 5. Annuler la reservation d'un agent

### Quand annuler ?

Vous pouvez annuler si :
- Le creneau **n'est pas verrouille**

### Etapes

1. Identifiez l'agent positionne
2. Cliquez sur **"Annuler"**
3. Confirmez l'annulation

### Apres annulation

- L'agent recoit un **email d'annulation** avec un lien pour se repositionner
- Le creneau est **libere**
- L'agent repasse en statut "Non positionne"

---

## 6. Vue Planning - Repartition par jour

### Acces

Depuis l'interface equipe, cliquez sur **"Vue Planning"**

### Contenu

Le planning affiche :
- Les **jours de la campagne** en colonnes
- Les **agents positionnes** par jour
- Le **taux de concentration** par jour

### Alerte concentration (RG-127)

Un **badge d'alerte** (orange/rouge) apparait si :
- Plus de **50%** de votre equipe est positionnee le meme jour

**Pourquoi c'est important ?**
- Eviter de desorganiser le service
- Assurer une continuite d'activite
- Repartir les interventions sur plusieurs jours

### Actions depuis le planning

- Cliquez sur un agent pour voir/modifier sa reservation
- Cliquez sur un jour pour voir les creneaux disponibles

---

## 7. Bonnes pratiques

### Avant la campagne

1. **Communiquez** a votre equipe les dates de la campagne
2. **Encouragez** les agents a se positionner eux-memes
3. **Verifiez** regulierement le taux de positionnement

### Pendant la campagne

1. **Identifiez** les agents non positionnes (relance si besoin)
2. **Surveillez** les alertes de concentration
3. **Positionnez** les agents retardataires si necessaire

### Gestion des modifications

1. **Privilegiez** les demandes des agents (autonomie)
2. **Verifiez** les contraintes avant de modifier
3. **Informez** l'agent des changements effectues

---

## 8. Questions frequentes

### Je ne vois pas tous mes agents

Verifiez que :
- Les agents sont bien **rattaches a votre service** dans la base
- Les agents sont **actifs** (non desactives)
- Vous avez les **droits manager** sur ce perimetre

### Un agent veut changer mais son creneau est verrouille

En tant que manager, vous avez les memes contraintes de verrouillage.
Pour les cas exceptionnels :
1. Contactez le **coordinateur de campagne**
2. Ou le **support IT** pour une intervention manuelle

### Comment savoir qui a positionne un agent ?

Dans le detail de la reservation, vous verrez :
- **Type de positionnement** : Par l'agent / Par le manager / Par le coordinateur
- **Positionne par** : Nom de la personne (si applicable)

### Un agent de mon equipe n'a pas d'email OpsTracker

L'agent doit etre enregistre dans la base agents avec :
- Un **email valide**
- Un **booking token** genere

Contactez le coordinateur pour verifier/corriger sa fiche.

### Puis-je positionner un agent d'une autre equipe ?

Non, sauf si vous etes **coordinateur** avec un perimetre elargi (RG-114).
Contactez le manager concerne ou le coordinateur.

---

## 9. Interface Coordinateur

Si vous avez le role **Coordinateur**, vous avez acces a des fonctionnalites etendues :

### Perimetre elargi (RG-114)

- Gestion d'agents **hors de votre hierarchie directe**
- Acces aux services **delegues** a votre compte
- Meme fonctionnalites que le manager (positionner, modifier, annuler)

### Acces

Menu : **Coordinateur > Campagne > Agents**

---

## 10. Contacts utiles

| Besoin | Contact |
|--------|---------|
| Question fonctionnelle | Coordinateur de campagne |
| Probleme technique | Support IT CPAM |
| Demande de perimetre | Responsable applicatif |

---

## 11. Regles metier importantes

| Regle | Description |
|-------|-------------|
| **RG-121** | Un agent = un seul creneau par campagne |
| **RG-123** | Verrouillage J-2 (aucune modification possible) |
| **RG-124** | Manager ne voit que son equipe |
| **RG-125** | Tracabilite du positionnement (qui a positionne) |
| **RG-126** | Notification automatique a l'agent si positionne par un tiers |
| **RG-127** | Alerte visuelle si >50% de l'equipe le meme jour |

---

_Documentation OpsTracker V2.0.0 - CPAM 92_
