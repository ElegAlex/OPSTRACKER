# P6 - Audit Complementaire V2.1b/V2.1c

> **Date** : 2026-01-25
> **Version** : 2.1.0
> **Scope** : Securite, Validation, RGPD
> **Auditeur** : Claude Code (Opus 4.5)
> **Referentiel** : OWASP Top 10, RGPD Art. 25 (Privacy by Design)

---

## Resume Executif

Cet audit complementaire approfondi evalue les aspects securite, validation et RGPD des fonctionnalites V2.1b (Calendrier FullCalendar) et V2.1c (Notifications SMS). L'audit precedent (P6-V2.1) etait synthetique ; celui-ci est detaille.

**Verdict : INSUFFISANT (70/100)** - Corrections requises avant production.

### Points Forts
- API JSON correctement implementee avec JsonResponse
- Gestion des erreurs providers SMS robuste
- Opt-in RGPD respecte avant envoi
- Tests unitaires SMS bien couverts

### Points Critiques
- **FINDING-001** : XSS potentiel via innerHTML dans le modal calendrier
- **FINDING-002** : Numeros de telephone non masques dans les logs (RGPD)
- **FINDING-003** : Pas de protection contre les doubles envois SMS
- **FINDING-004** : Validation telephone incomplete (pas de rejet des invalides)

---

## Score Global

| Critere | Score | Statut |
|---------|-------|--------|
| AC-1 XSS Protection | 10/15 | **WARN** |
| AC-2 Injection JSON | 10/10 | PASS |
| AC-3 Permissions | 8/10 | **WARN** |
| AC-4 Validation Telephone | 7/15 | **FAIL** |
| AC-5 Rate Limiting | 4/10 | **FAIL** |
| AC-6 Gestion Erreurs | 10/10 | PASS |
| AC-7 RGPD | 9/15 | **FAIL** |
| AC-8 Tests | 12/15 | **WARN** |
| **TOTAL** | **70/100** | **INSUFFISANT** |

---

## Detail des Criteres

### AC-1 — XSS Protection Calendrier [10/15]

#### Constats Positifs

| Check | Points | Evidence |
|-------|--------|----------|
| JsonResponse encode automatiquement | 5/5 | `ManagerCalendarController.php:165` |
| Echappement Twig pour donnees statiques | 5/5 | Variables campagne, manager |

**Code securise identifie :**
```php
// Ligne 165 - Encodage JSON automatique et securise
return new JsonResponse($events);
```

#### Finding CRITICAL

**FINDING-001** : innerHTML avec donnees utilisateur dans le modal calendrier.

**Fichier** : `templates/manager/calendar.html.twig:353`

**Code problematique :**
```javascript
// Ligne 353 - RISQUE XSS
content.innerHTML = html;

// Les donnees injectees incluent :
// - agent.prenom, agent.nom (ligne 328-330)
// - props.lieu (ligne 298)
// - startTime, endTime (lignes 289-290, dates formatees)
```

**Scenario d'attaque** : Un agent malveillant pourrait injecter du code XSS dans son nom/prenom via l'import CSV ou l'API d'administration :
```
Prenom: <img src=x onerror="alert(document.cookie)">
```

**Impact** : Vol de session, defacement, actions non autorisees.

**Recommandation** : Utiliser `textContent` pour les donnees textuelles ou echapper avec une fonction dediee :
```javascript
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Utilisation
html += `<span class="font-medium text-sm">${escapeHtml(agent.prenom)} ${escapeHtml(agent.nom)}</span>`;
```

---

### AC-2 — Injection JSON API Calendrier [10/10] PASS

#### Constats

| Check | Points | Evidence |
|-------|--------|----------|
| Parametres start/end valides comme DateTime | 4/4 | Lignes 81-87 avec try/catch |
| Pas de concatenation SQL | 3/3 | Repository utilise QueryBuilder |
| QueryBuilder avec parametres bindes | 3/3 | `CreneauRepository.php:110-114` |

**Code securise :**
```php
// ManagerCalendarController.php:81-87 - Validation dates
try {
    $start = $startParam ? new \DateTime($startParam) : new \DateTime('first day of this month');
    $end = $endParam ? new \DateTime($endParam) : new \DateTime('last day of next month');
} catch (\Exception $e) {
    $start = new \DateTime('first day of this month');
    $end = new \DateTime('last day of next month');
}

// CreneauRepository.php:110-114 - Parametres bindes
->setParameter('campagne', $campagne)
->setParameter('debut', $debut->format('Y-m-d'))
->setParameter('fin', $fin->format('Y-m-d'))
```

---

### AC-3 — Permissions Calendrier [8/10] WARN

#### Constats Positifs

| Check | Points | Evidence |
|-------|--------|----------|
| IsGranted sur classe | 4/4 | `#[IsGranted('ROLE_GESTIONNAIRE')]` ligne 25 |
| Filtrage par manager | 3/4 | `findByManager($manager)` ligne 93 |

**Code securise :**
```php
// Ligne 25 - Protection au niveau classe
#[IsGranted('ROLE_GESTIONNAIRE')]
class ManagerCalendarController extends AbstractController

// Ligne 93 - Filtrage equipe
$mesAgents = $this->agentRepository->findByManager($manager);
```

#### Finding MEDIUM

**FINDING-005** : IDOR potentiel sur l'acces aux campagnes.

**Fichier** : `ManagerCalendarController.php`

**Description** : Le controller accepte n'importe quel ID de campagne sans verifier que le manager y a acces. Un manager pourrait visualiser les creneaux d'une campagne a laquelle il n'appartient pas.

**Impact** : Fuite d'informations sur les plannings d'autres equipes.

**Recommandation** : Ajouter un Voter ou une verification explicite :
```php
// Verifier que le manager appartient a cette campagne
if (!$this->isGranted('VIEW', $campagne)) {
    throw $this->createAccessDeniedException();
}
```

---

### AC-4 — Validation Telephone E.164 [7/15] FAIL

#### Constats Positifs

| Check | Points | Evidence |
|-------|--------|----------|
| Normalisation format E.164 | 5/5 | `Agent.php:224-232` |

**Code existant :**
```php
// Agent.php:224-232
public function setTelephone(?string $telephone): static
{
    if ($telephone !== null) {
        // Supprimer tous les caracteres non numeriques sauf le +
        $telephone = preg_replace('/[^0-9+]/', '', $telephone);
        // Convertir les numeros francais commencant par 0 en format E.164
        if (str_starts_with($telephone, '0')) {
            $telephone = '+33' . substr($telephone, 1);
        }
    }
    $this->telephone = $telephone;
    return $this;
}
```

#### Findings

**FINDING-004** (HIGH) : Pas de validation regex ni de rejet des numeros invalides.

**Impact** : Des numeros invalides (trop courts, mauvais format) peuvent etre stockes et provoquer des echecs d'envoi ou des couts inutiles.

**Recommandation** :
```php
public function setTelephone(?string $telephone): static
{
    if ($telephone !== null) {
        $telephone = preg_replace('/[^0-9+]/', '', $telephone);

        if (str_starts_with($telephone, '0')) {
            $telephone = '+33' . substr($telephone, 1);
        }

        // AJOUTER : Validation E.164 stricte
        if (!preg_match('/^\+[1-9]\d{6,14}$/', $telephone)) {
            throw new \InvalidArgumentException(
                sprintf('Numero de telephone invalide : %s', $telephone)
            );
        }
    }
    $this->telephone = $telephone;
    return $this;
}
```

**FINDING-006** (MEDIUM) : Pas de constraint Symfony sur l'entite.

**Recommandation** : Ajouter une validation declarative :
```php
#[ORM\Column(length: 20, nullable: true)]
#[Assert\Regex(
    pattern: '/^\+[1-9]\d{6,14}$/',
    message: 'Le telephone doit etre au format E.164 (+33612345678)'
)]
private ?string $telephone = null;
```

**FINDING-007** (LOW) : Pas de tests unitaires pour la validation telephone.

---

### AC-5 — Rate Limiting SMS [4/10] FAIL

#### Constats

| Check | Points | Evidence |
|-------|--------|----------|
| Logging des envois pour audit | 3/3 | Table Notification |
| Pas de double envoi meme reservation | 0/4 | **ABSENT** |
| Limite par agent/jour | 1/3 | Non implemente |

#### Finding HIGH

**FINDING-003** : Pas de protection contre les doubles envois SMS.

**Fichier** : `SmsService.php`

**Description** : La methode `hasRappelEnvoye()` du NotificationRepository verifie uniquement le type `TYPE_RAPPEL` (email), pas `TYPE_RAPPEL_SMS`. Un agent peut recevoir plusieurs SMS de rappel pour la meme reservation.

**Evidence** :
```php
// NotificationRepository.php:145 - Ne verifie que les emails
->setParameter('type', Notification::TYPE_RAPPEL)  // PAS TYPE_RAPPEL_SMS
```

**Impact** :
- Surcout SMS (facturation au message)
- Spam de l'agent (experience degradee)
- Non-conformite RGPD (principe de minimisation)

**Recommandation** :
```php
// Dans NotificationRepository.php - Ajouter :
public function hasRappelSmsEnvoye(Reservation $reservation): bool
{
    $count = $this->createQueryBuilder('n')
        ->select('COUNT(n.id)')
        ->andWhere('n.reservation = :reservation')
        ->andWhere('n.type = :type')
        ->andWhere('n.statut = :statut')
        ->setParameter('reservation', $reservation)
        ->setParameter('type', Notification::TYPE_RAPPEL_SMS)
        ->setParameter('statut', Notification::STATUT_SENT)
        ->getQuery()
        ->getSingleScalarResult();
    return $count > 0;
}

// Dans SmsService::envoyerRappel() - Ajouter verification :
public function envoyerRappel(Reservation $reservation): bool
{
    // Verifier doublon
    if ($this->notificationRepository->hasRappelSmsEnvoye($reservation)) {
        $this->logger->debug('[SMS] Rappel deja envoye', ['reservation' => $reservation->getId()]);
        return false;
    }
    // ... suite du code
}
```

---

### AC-6 — Gestion Erreurs Provider SMS [10/10] PASS

#### Constats

| Check | Points | Evidence |
|-------|--------|----------|
| Try/catch autour appel provider | 3/3 | `OvhSmsProvider.php:35-59` |
| Logging erreur avec contexte | 3/3 | Lignes 53-56 |
| Notification marquee 'failed' si erreur | 2/2 | `SmsService.php:147` |
| Pas d'exception non catchee | 2/2 | Return false + batch continue |

**Code exemplaire :**
```php
// OvhSmsProvider.php:52-59
} catch (\Exception $e) {
    $this->logger->error('[SMS OVH] Erreur envoi', [
        'to' => $to,
        'error' => $e->getMessage(),
    ]);
    return false;
}

// SmsService.php:146-152
if ($success) {
    $notification->markAsSent();
} else {
    $notification->markAsFailed('Erreur envoi provider ' . $this->provider->getProviderName());
}
```

---

### AC-7 — Conformite RGPD SMS [9/15] FAIL

#### Constats Positifs

| Check | Points | Evidence |
|-------|--------|----------|
| Opt-in explicite requis | 4/4 | `smsOptIn` boolean + canReceiveSms() |
| Verification canReceiveSms() avant envoi | 4/4 | `SmsService.php:108` |
| Possibilite de retrait consentement | 2/2 | `setSmsOptIn(false)` |

**Code conforme :**
```php
// Agent.php:252-255
public function canReceiveSms(): bool
{
    return $this->smsOptIn && !empty($this->telephone);
}

// SmsService.php:100-118
private function canSend(Agent $agent): bool
{
    if (!$this->smsEnabled) { return false; }
    if (!$agent->canReceiveSms()) { return false; }
    return true;
}
```

#### Finding CRITICAL

**FINDING-002** : Numeros de telephone non masques dans les logs.

**Fichiers** :
- `OvhSmsProvider.php:46` et `54`
- `LogSmsProvider.php:23`

**Code non-conforme :**
```php
// OvhSmsProvider.php:45-48
$this->logger->info('[SMS OVH] Message envoye', [
    'to' => $to,  // NUMERO COMPLET EN CLAIR
    ...
]);

// LogSmsProvider.php:22-26
$this->logger->info('[SMS LOG MODE] Message simule', [
    'to' => $to,  // NUMERO COMPLET EN CLAIR
    ...
]);
```

**Impact RGPD** :
- Violation du principe de minimisation (Art. 5.1.c)
- Violation de la securite des donnees (Art. 32)
- Risque de sanctions CNIL jusqu'a 4% du CA

**Recommandation** :
```php
// Fonction de masquage
private function maskPhone(string $phone): string
{
    return substr($phone, 0, 6) . '****' . substr($phone, -2);
    // +33612****78
}

// Utilisation dans les logs
$this->logger->info('[SMS OVH] Message envoye', [
    'to' => $this->maskPhone($to),
    ...
]);
```

#### Finding MEDIUM

**FINDING-008** : Duree de conservation non definie.

**Description** : Les notifications SMS sont conservees indefiniment. Le RGPD impose une duree de conservation limitee et proportionnee.

**Recommandation** :
- Definir une politique de retention (ex: 13 mois)
- Implementer une commande de purge : `app:purge-notifications --days=395`

---

### AC-8 — Tests Specifiques V2.1b/V2.1c [12/15] WARN

#### Constats

| Check | Points | Evidence |
|-------|--------|----------|
| Test API calendrier JSON valide | 3/3 | `ManagerCalendarControllerTest.php:113-146` |
| Test calendrier filtre equipe manager | 2/3 | Partiellement teste |
| Test SMS opt-in respecte | 3/3 | `SmsServiceTest.php:53-76` |
| Test SMS opt-out bloque | 3/3 | `SmsServiceTest.php:78-90` |
| Test telephone invalide rejete | 1/3 | **ABSENT** |

**Tests existants (conformes)** :
- `testEnvoyerRappelAgentOptInAvecTelephone` : Verifie que le SMS est envoye
- `testEnvoyerRappelAgentSansOptIn` : Verifie que le SMS est bloque
- `testEnvoyerRappelAgentSansTelephone` : Verifie que le SMS est bloque
- `testCalendarEventsReturnsJson` : Verifie le format JSON

#### Finding LOW

**FINDING-009** : Tests manquants pour la validation telephone.

**Recommandation** : Ajouter dans `tests/Unit/Entity/AgentTest.php` :
```php
public function testSetTelephoneNormalise06(): void
{
    $agent = new Agent();
    $agent->setTelephone('06 12 34 56 78');
    $this->assertEquals('+33612345678', $agent->getTelephone());
}

public function testSetTelephoneRejetteInvalide(): void
{
    $this->expectException(\InvalidArgumentException::class);
    $agent = new Agent();
    $agent->setTelephone('123'); // Trop court
}
```

---

## Liste des Findings

| ID | Severite | Critere | Description | Fichier |
|----|----------|---------|-------------|---------|
| FINDING-001 | **CRITICAL** | AC-1 | XSS via innerHTML dans modal calendrier | `calendar.html.twig:353` |
| FINDING-002 | **CRITICAL** | AC-7 | Telephone non masque dans logs (RGPD) | `OvhSmsProvider.php:46,54` |
| FINDING-003 | **HIGH** | AC-5 | Pas de protection double envoi SMS | `SmsService.php` |
| FINDING-004 | **HIGH** | AC-4 | Validation telephone incomplete | `Agent.php:222-235` |
| FINDING-005 | **MEDIUM** | AC-3 | IDOR potentiel sur campagnes | `ManagerCalendarController.php` |
| FINDING-006 | **MEDIUM** | AC-4 | Pas de constraint Symfony telephone | `Agent.php` |
| FINDING-007 | **LOW** | AC-4 | Tests validation telephone manquants | `tests/` |
| FINDING-008 | **MEDIUM** | AC-7 | Duree conservation non definie | `Notification.php` |
| FINDING-009 | **LOW** | AC-8 | Tests telephone invalide manquants | `tests/` |

---

## Recommandations Priorisees

| # | Finding | Severite | Effort | Sprint | Description |
|---|---------|----------|--------|--------|-------------|
| 1 | FINDING-002 | CRITICAL | 1h | **Immediat** | Masquer telephones dans logs providers |
| 2 | FINDING-001 | CRITICAL | 2h | **Immediat** | Echapper donnees HTML dans modal calendrier |
| 3 | FINDING-003 | HIGH | 2h | V2.1.1 | Ajouter verification doublon SMS |
| 4 | FINDING-004 | HIGH | 1h | V2.1.1 | Valider et rejeter telephones invalides |
| 5 | FINDING-005 | MEDIUM | 2h | V2.1.1 | Ajouter Voter campagne pour IDOR |
| 6 | FINDING-006 | MEDIUM | 30m | V2.1.1 | Ajouter Assert\Regex sur telephone |
| 7 | FINDING-008 | MEDIUM | 3h | V2.2 | Politique retention + commande purge |
| 8 | FINDING-007 | LOW | 1h | V2.2 | Tests unitaires validation telephone |
| 9 | FINDING-009 | LOW | 1h | V2.2 | Tests supplementaires telephone invalide |

**Effort total corrections critiques/high** : ~6h (Sprint correctif V2.1.1)

---

## Plan de Remediation

### Phase 1 - Immediat (Avant MEP)

```php
// 1. Masquer telephones - OvhSmsProvider.php
private function maskPhone(string $phone): string
{
    $len = strlen($phone);
    if ($len <= 6) return str_repeat('*', $len);
    return substr($phone, 0, 4) . str_repeat('*', $len - 6) . substr($phone, -2);
}

// 2. Echapper HTML - calendar.html.twig
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
```

### Phase 2 - Sprint V2.1.1

1. **Doublon SMS** : Implementer `hasRappelSmsEnvoye()` + appel dans `SmsService`
2. **Validation telephone** : Ajouter regex + exception + Assert
3. **IDOR campagne** : Creer `CampagneVoter` ou verification explicite

### Phase 3 - Sprint V2.2

1. **Retention** : Commande `app:purge-notifications`
2. **Tests** : Couverture validation telephone

---

## Conclusion

L'audit complementaire revele **2 vulnerabilites critiques** (XSS et RGPD) et **2 problemes high** qui necessitent un sprint correctif avant mise en production.

**Verdict : INSUFFISANT (70/100)**

**Actions requises :**
1. **Blocage MEP** jusqu'a correction FINDING-001 et FINDING-002
2. **Sprint V2.1.1** pour les corrections HIGH
3. **Mise a jour PROGRESS-V2.md** avec les taches de remediation

---

*Audit realise avec Claude Code (Opus 4.5) - 2026-01-25*
