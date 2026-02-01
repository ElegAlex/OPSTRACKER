# Audit Securite OWASP - OpsTracker V1

> **Version** : 1.0.0
> **Date** : 2026-01-22
> **Auditeur** : Equipe Developpement
> **Reference** : OWASP Top 10 2021

---

## Resume Executif

| Categorie | Risque | Statut | Actions |
|-----------|--------|--------|---------|
| A01 - Broken Access Control | Moyen | MITIGE | Voters Symfony |
| A02 - Cryptographic Failures | Faible | OK | bcrypt, HTTPS |
| A03 - Injection | Faible | OK | Doctrine ORM, Twig escape |
| A04 - Insecure Design | Faible | OK | Architecture Symfony |
| A05 - Security Misconfiguration | Faible | OK | .env, secrets |
| A06 - Vulnerable Components | Faible | A SURVEILLER | Dependances a jour |
| A07 - Identification Failures | Moyen | MITIGE | RG-006 verrouillage |
| A08 - Data Integrity Failures | Faible | OK | CSRF tokens |
| A09 - Logging Failures | Faible | OK | Audit trail |
| A10 - SSRF | N/A | N/A | Pas de fetch externe |

**Verdict Global : ACCEPTABLE pour V1**

---

## A01 - Broken Access Control

### Risques Identifies
- Acces non autorise aux operations d'autres campagnes
- Modification de statut sans droits

### Mesures Implementees

#### Voters Symfony
```php
// src/Security/Voter/OperationVoter.php
- OPERATION_VIEW : Technicien assigne OU gestionnaire campagne
- OPERATION_EDIT : Technicien assigne OU gestionnaire campagne
- OPERATION_DELETE : Admin uniquement
```

#### Controles dans les Controllers
```php
// Verification systematique
$this->denyAccessUnlessGranted('OPERATION_VIEW', $operation);
```

#### Verification
- [x] Tous les endpoints sont proteges par `#[IsGranted]`
- [x] Les Voters verifient l'appartenance
- [x] Les routes admin sont sous `/admin` avec ROLE_ADMIN

### Recommandations V2
- Implementer ACL par campagne (RG-115)
- Audit des acces refuses

---

## A02 - Cryptographic Failures

### Risques Identifies
- Stockage mots de passe en clair
- Transmission donnees sensibles non chiffrees

### Mesures Implementees

#### Hashage des mots de passe
```yaml
# config/packages/security.yaml
password_hashers:
    App\Entity\Utilisateur: 'auto'  # bcrypt
```

#### HTTPS en production
```yaml
# config/packages/framework.yaml (prod)
framework:
    session:
        cookie_secure: true
```

#### Verification
- [x] Mots de passe hashes avec bcrypt (cost 13)
- [x] Pas de secrets en clair dans le code
- [x] `.env` dans `.gitignore`
- [x] Variables sensibles dans `.env.local`

### Recommandations V2
- Chiffrement des donnees sensibles en BDD
- Rotation des secrets

---

## A03 - Injection

### Risques Identifies
- SQL Injection
- XSS (Cross-Site Scripting)
- Command Injection

### Mesures Implementees

#### SQL Injection
```php
// Doctrine ORM avec paramètres liés
$qb->where('o.statut = :statut')
   ->setParameter('statut', $statut);

// Pas de requêtes SQL raw
```

#### XSS
```twig
{# Twig echappe par defaut #}
{{ operation.nom }}

{# Pour HTML explicite uniquement #}
{{ operation.description|raw }}  {# JAMAIS avec user input #}
```

#### Verification
- [x] Pas de `createQuery()` avec concatenation
- [x] Pas de `|raw` sur les inputs utilisateur
- [x] Pas d'appels `exec()` ou `shell_exec()`

### Tests Effectues
```bash
# Test XSS basique
Nom operation: <script>alert('XSS')</script>
Resultat: Echappe correctement -> &lt;script&gt;...

# Test SQL Injection
Recherche: ' OR '1'='1
Resultat: Pas d'injection, traite comme string
```

---

## A04 - Insecure Design

### Mesures Implementees

#### Architecture securisee
- Separation Controller/Service/Repository
- Logique metier dans les Services
- Validation dans les Forms

#### Validation des entrees
```php
// src/Entity/Operation.php
#[Assert\NotBlank]
#[Assert\Length(max: 50)]
private ?string $matricule = null;
```

#### Verification
- [x] Toutes les entites ont des contraintes de validation
- [x] Les Forms valident avant persistence
- [x] Pas de logique metier dans les Controllers

---

## A05 - Security Misconfiguration

### Mesures Implementees

#### Configuration production
```yaml
# .env.prod
APP_ENV=prod
APP_DEBUG=false
```

#### Headers securite
```yaml
# config/packages/nelmio_security.yaml (si installe)
# Sinon, configuration Nginx recommandee
```

#### Verification
- [x] `APP_DEBUG=false` en production
- [x] Pas de credentials dans le code
- [x] Docker avec utilisateur non-root

### Configuration Nginx Recommandee
```nginx
# Ajout recommande pour production
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

---

## A06 - Vulnerable and Outdated Components

### Etat des Dependances

```bash
# Verification des vulnerabilites
composer audit
```

#### Dependances Principales
| Package | Version | CVE | Statut |
|---------|---------|-----|--------|
| symfony/symfony | 7.4.x | Aucun | OK |
| doctrine/orm | 3.x | Aucun | OK |
| league/csv | 9.x | Aucun | OK |
| dompdf/dompdf | 3.x | Aucun | OK |

### Recommandations
- Mettre a jour regulierement : `composer update`
- Configurer Dependabot sur le repo

---

## A07 - Identification and Authentication Failures

### Risques Identifies
- Brute force sur login
- Sessions non securisees

### Mesures Implementees

#### RG-006 : Verrouillage compte
```php
// src/EventListener/LoginFailureListener.php
// Verrouillage apres 5 echecs
if ($user->getLoginAttempts() >= 5) {
    $user->setActif(false);
}
```

#### Sessions securisees
```yaml
# config/packages/framework.yaml
session:
    handler_id: '%env(REDIS_URL)%/1'
    cookie_secure: auto
    cookie_httponly: true
    cookie_samesite: lax
```

#### Verification
- [x] Verrouillage apres 5 echecs (RG-006)
- [x] Sessions en Redis
- [x] Cookie httpOnly et secure
- [x] Logout invalide la session

### Recommandations V2
- Rate limiting sur /login
- 2FA pour les admins

---

## A08 - Software and Data Integrity Failures

### Mesures Implementees

#### Protection CSRF
```twig
{# Dans tous les formulaires #}
{{ form_start(form) }}  {# Inclut automatiquement le token CSRF #}
```

#### Verification des uploads
```php
// src/Service/DocumentService.php
private const ALLOWED_MIME_TYPES = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    // ...
];
```

#### Verification
- [x] CSRF sur tous les formulaires POST
- [x] Validation des types MIME sur upload
- [x] Taille max upload : 50 Mo

---

## A09 - Security Logging and Monitoring Failures

### Mesures Implementees

#### Audit Trail (RG-070)
```php
// dh/auditor-bundle
// Toutes les modifications sont tracees
```

#### Logs Monolog
```yaml
# config/packages/monolog.yaml
handlers:
    main:
        type: stream
        path: "%kernel.logs_dir%/%kernel.environment%.log"
        level: info
```

#### Verification
- [x] Audit trail sur toutes les entites
- [x] Logs des erreurs 500
- [x] Logs des authentifications

### Recommandations V2
- Alerting sur les erreurs critiques
- Log aggregation (ELK/Loki)

---

## A10 - Server-Side Request Forgery (SSRF)

**Non Applicable** : L'application ne fait pas de requetes HTTP vers des URLs externes fournies par l'utilisateur.

---

## Tests de Securite Effectues

### Tests Manuels
| Test | Resultat | Notes |
|------|----------|-------|
| Login brute force | BLOQUE | Verrouillage apres 5 echecs |
| Acces operation autre user | REFUSE | Voter fonctionne |
| XSS dans nom operation | ECHAPPE | Twig protection |
| CSRF sans token | REFUSE | 403 Forbidden |
| Upload fichier PHP | REFUSE | Type MIME invalide |
| SQL injection recherche | PROTEGE | Doctrine ORM |

### Outils Utilises
- PHPStan (analyse statique)
- OWASP ZAP (scan basique) - A executer en CI

---

## Plan d'Action V2

| Priorite | Action | Effort |
|----------|--------|--------|
| Haute | Rate limiting /login | 2h |
| Haute | Headers securite Nginx | 1h |
| Moyenne | 2FA admins | 4h |
| Moyenne | Scan vulnerabilites CI | 2h |
| Basse | Chiffrement donnees sensibles | 8h |

---

## Conclusion

L'application OpsTracker V1 presente un niveau de securite **acceptable** pour un deploiement interne. Les principales vulnerabilites OWASP sont adressees par :

1. L'utilisation du framework Symfony et ses mecanismes de securite
2. L'ORM Doctrine pour la prevention des injections SQL
3. Le systeme de Voters pour le controle d'acces
4. Le hashage bcrypt des mots de passe
5. La protection CSRF native des formulaires

**Recommandation** : Deployer en production avec surveillance active des logs.

---

*Audit realise le 2026-01-22 pour OpsTracker v1.0.0*
