# Guide Agent - Reserver un creneau

> **OpsTracker V2** - Module Reservation
> Documentation utilisateur pour les agents

---

## 1. Acceder a l'interface de reservation

### Via le lien personnalise

1. Vous recevez un email d'invitation contenant un **lien unique** de reservation
2. Cliquez sur ce lien pour acceder directement a l'interface
3. Aucune connexion n'est requise (le lien est securise et personnel)

**Important** : Ce lien est strictement personnel. Ne le partagez pas avec d'autres collegues.

---

## 2. Consulter les creneaux disponibles

### Page d'accueil de reservation

Une fois sur l'interface, vous verrez :

- **Votre nom** et service en haut de page
- La **campagne en cours** (nom, dates)
- La **liste des creneaux disponibles** groupes par date

### Informations affichees pour chaque creneau

| Element | Description |
|---------|-------------|
| Date | Jour du rendez-vous |
| Horaire | Heure de debut - Heure de fin |
| Lieu | Salle ou bureau d'intervention |
| Places | Nombre de places restantes |

### Creneaux non visibles

Certains creneaux peuvent ne pas apparaitre :
- Creneaux **complets** (plus de place disponible)
- Creneaux **verrouilles** (moins de 2 jours avant l'intervention)
- Creneaux reserves a un autre **segment/site**

---

## 3. Choisir et confirmer un creneau

### Etape 1 : Selectionner un creneau

1. Parcourez les dates disponibles
2. Identifiez le creneau qui vous convient
3. Cliquez sur le bouton **"Choisir ce creneau"**

### Etape 2 : Confirmation automatique

Apres selection :
- Votre reservation est **immediatement confirmee**
- Un **email de confirmation** vous est envoye
- Le fichier **ICS** (invitation calendrier) est joint a l'email

### Ajouter a votre agenda Outlook

1. Ouvrez l'email de confirmation
2. Double-cliquez sur la piece jointe `.ics`
3. Outlook vous propose d'ajouter l'evenement
4. Cliquez sur **"Accepter"**

Le rendez-vous apparaitra dans votre calendrier avec :
- Un rappel **1 jour avant**
- Un rappel **1 heure avant**

---

## 4. Consulter votre recapitulatif

### Page recapitulatif

Apres reservation, vous pouvez a tout moment :

1. Retourner sur l'interface via le lien de votre email
2. Consulter votre **recapitulatif complet** :
   - Date et heure du creneau
   - Lieu de l'intervention
   - Informations sur la campagne
3. Telecharger a nouveau le fichier **ICS**

---

## 5. Modifier votre creneau

### Quand modifier ?

Vous pouvez modifier votre creneau si :
- Le creneau **n'est pas verrouille** (plus de 2 jours avant)
- D'autres creneaux sont **disponibles**

### Comment modifier ?

1. Accedez a votre recapitulatif
2. Cliquez sur **"Modifier mon creneau"**
3. Selectionnez un nouveau creneau parmi ceux disponibles
4. Confirmez le changement

### Apres modification

- Vous recevez un **email de confirmation** avec les nouvelles informations
- L'ancien creneau est **libere** pour d'autres agents
- Un nouveau fichier **ICS** est genere

---

## 6. Annuler votre reservation

### Quand annuler ?

Vous pouvez annuler votre reservation si :
- Le creneau **n'est pas verrouille** (plus de 2 jours avant)

### Comment annuler ?

1. Accedez a votre recapitulatif
2. Cliquez sur **"Annuler ma reservation"**
3. Confirmez l'annulation

### Apres annulation

- Vous recevez un **email de confirmation** d'annulation
- Le creneau est **libere**
- Vous pouvez vous repositionner sur un autre creneau

---

## 7. Rappels automatiques

Vous recevrez des rappels par email :

| Moment | Contenu |
|--------|---------|
| **J-2** | Rappel de votre rendez-vous avec les details |
| **J-1** (optionnel) | Second rappel si configure |

Ces rappels contiennent un lien pour **modifier** votre creneau si necessaire (et si non verrouille).

---

## 8. Questions frequentes

### Je n'ai pas recu l'email d'invitation

1. Verifiez votre dossier **spam/courrier indesirable**
2. Contactez votre manager ou le coordinateur de campagne
3. Ils peuvent vous renvoyer l'invitation

### Le lien ne fonctionne plus

Le lien reste valide pendant toute la duree de la campagne. Si vous rencontrez une erreur :
1. Verifiez que vous utilisez le **lien complet**
2. Essayez de copier/coller le lien plutot que de cliquer
3. Contactez votre manager

### Je ne vois aucun creneau disponible

Cela peut signifier :
- Tous les creneaux sont **complets**
- Tous les creneaux restants sont **verrouilles**
- Aucun creneau n'est prevu pour votre **site/segment**

Contactez votre manager pour qu'il vous positionne ou demande l'ouverture de nouveaux creneaux.

### Je dois modifier mais mon creneau est verrouille

Si votre creneau est verrouille (moins de 2 jours avant), seul votre **manager** peut effectuer des modifications exceptionnelles. Contactez-le directement.

### Mon manager m'a positionne sur un creneau

Si votre manager ou coordinateur vous a positionne :
1. Vous recevez un **email de notification** avec les details
2. Vous pouvez consulter votre recapitulatif via le lien habituel
3. Vous pouvez **modifier** le creneau si vous le souhaitez (si non verrouille)

---

## 9. Contacts utiles

| Besoin | Contact |
|--------|---------|
| Question sur mon creneau | Mon manager direct |
| Probleme technique | Support IT |
| Informations campagne | Coordinateur de campagne |

---

## 10. Notifications SMS (optionnel)

### Activer les rappels SMS

Si votre organisation a active les notifications SMS :

1. Accedez a votre **recapitulatif** de reservation
2. Cliquez sur **"Activer les rappels SMS"**
3. Saisissez votre **numero de telephone portable**
4. Validez

### Rappels recus

- **J-2** : Rappel SMS de votre rendez-vous
- **J** (matin) : Rappel le jour meme (si configure)

**Note** : Cette fonctionnalite est optionnelle et depend de la configuration de votre organisation.

---

## 11. Acces via lien de campagne (mode public)

### Alternative au lien personnel

Certaines campagnes utilisent un **lien de campagne** plutot qu'un lien personnel :

1. Vous recevez un lien du type `/reservation/c/XXXXXX`
2. Vous devez vous **identifier** (email ou nom/prenom)
3. Le systeme verifie que vous etes autorise a reserver
4. Vous accedez ensuite aux creneaux disponibles

### Difference avec le lien personnel

| Lien personnel | Lien campagne |
|----------------|---------------|
| `/reservation/{votre-token}` | `/reservation/c/{token-campagne}` |
| Pas d'identification | Identification requise |
| Acces direct aux creneaux | Verification d'autorisation |

---

## 12. Regles importantes

- **Un seul creneau par campagne** : Vous ne pouvez avoir qu'une seule reservation active
- **Verrouillage J-2** : Les modifications sont impossibles 2 jours avant l'intervention
- **Ponctualite** : Presentez-vous a l'heure indiquee avec votre materiel
- **Annulation** : En cas d'absence, prevenez au plus vite pour liberer la place

---

_Documentation OpsTracker V2.1.0 - Organisation_
