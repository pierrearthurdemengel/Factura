<div align="center">

# Ma Facture Pro

### La facturation électronique conforme, simple et élégante.

[![CI](https://github.com/pierrearthur-demengel/factura/actions/workflows/ci.yaml/badge.svg)](https://github.com/pierrearthur-demengel/factura/actions)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen?logo=php)](https://phpstan.org/)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php)](https://www.php.net/)
[![Symfony 7](https://img.shields.io/badge/Symfony-7.2-black?logo=symfony)](https://symfony.com/)
[![React 18](https://img.shields.io/badge/React-18-61DAFB?logo=react)](https://react.dev/)
[![TypeScript](https://img.shields.io/badge/TypeScript-strict-3178C6?logo=typescript)](https://www.typescriptlang.org/)
[![API Platform](https://img.shields.io/badge/API%20Platform-3.3-38A89D?logo=api-platform)](https://api-platform.com/)
[![PostgreSQL 16](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

<br />

**SaaS de facturation électronique pour freelances et PME françaises.**
**Conforme à la réforme DGFiP 2026-2027 · Candidat au label "Solution compatible".**

<br />

[🚀 Démarrer](#-démarrage-rapide) · [📖 Documentation API](#-api-rest) · [🏗️ Architecture](#%EF%B8%8F-architecture) · [🧪 Tests](#-tests--qualité) · [📦 Déploiement](#-déploiement)

</div>

---

## 🎯 Pourquoi Ma Facture Pro ?

À partir de **septembre 2026**, toutes les entreprises françaises doivent recevoir des factures électroniques. L'émission devient obligatoire progressivement jusqu'en **septembre 2027**. Environ **8 millions d'acteurs économiques** sont concernés.

Ma Facture Pro est la réponse open-source à ce besoin :

| | Ma Facture Pro | Solutions existantes |
|---|---|---|
| 💰 **Prix** | **Gratuit** jusqu'à 30 factures/mois | 15-50 €/mois |
| 📄 **Formats** | Factur-X, UBL 2.1, CII D16B | Souvent un seul format |
| 🔌 **PDP** | Chorus Pro intégré (PISTE OAuth2) | Connexion manuelle |
| 🏷️ **Label DGFiP** | Dossier constitué, candidature prête | Rarement labellisé |
| 🔒 **Archivage** | S3 France, SHA-256, 10 ans | Variable |
| 🧾 **PAF** | Piste d'audit fiable immutable | Souvent absente |

---

## ✨ Fonctionnalités

<table>
<tr>
<td width="50%">

### 📝 Création de factures
- Saisie guidée avec calcul automatique HT/TVA/TTC
- Multi-taux TVA (0%, 2.1%, 5.5%, 10%, 20%)
- Prévisualisation en temps réel
- Conditions de paiement et mentions légales
- Numérotation séquentielle `FA-AAAA-NNNN`

### 📄 Formats réglementaires
- **Factur-X** — PDF/A-3 avec XML CII D16B embarqué
- **UBL 2.1** — Peppol BIS Billing 3.0
- **CII D16B** — Cross Industry Invoice standalone
- Profil **EN 16931** garanti
- Lecture de factures entrantes (Factur-X Reader)

### 🔌 Intégration PDP
- **Chorus Pro** via API PISTE OAuth2
- Soumission automatique des factures
- Suivi du statut en temps réel
- Webhook de callback PDP
- Mode sandbox pour les tests

</td>
<td width="50%">

### 🔐 Sécurité & conformité
- Authentification JWT RS256 + refresh token
- Isolation multi-tenant stricte (QueryExtension)
- Voters Symfony (VIEW, EDIT, DELETE, SEND, CANCEL)
- Validation SIREN (Luhn modifié), IBAN, TVA intra
- Toutes les mentions DGFiP obligatoires

### 🗂️ Archivage & PAF
- Stockage S3 en France (Scaleway)
- Hash SHA-256 pour l'intégrité
- Versioning activé, aucune suppression possible
- Journal d'événements immutable (InvoiceEvent)
- Conservation 10 ans conforme art. 289 VII CGI

### 💳 Abonnements Stripe
- Plans : Gratuit (30/mois), Pro (12 €), Équipe (29 €)
- Webhooks Stripe complets
- Portail de facturation intégré
- Contrôle de quotas automatique

</td>
</tr>
</table>

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        FRONTEND                                  │
│              React 18 · TypeScript · Vite                        │
│                                                                  │
│   ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐          │
│   │Dashboard │ │ Factures │ │ Création │ │Paramètres│          │
│   └──────────┘ └──────────┘ └──────────┘ └──────────┘          │
│                         │                                        │
│                    axios + JWT                                    │
└─────────────────────────┼───────────────────────────────────────┘
                          │ HTTPS / JSON-LD
┌─────────────────────────┼───────────────────────────────────────┐
│                     API PLATFORM 3                               │
│              Symfony 7.2 · PHP 8.3                               │
│                                                                  │
│   ┌───────────────────────────────────────────────────────┐     │
│   │                   CONTROLLERS                          │     │
│   │  Auth · InvoiceExport · InvoiceEvent · PdpCallback    │     │
│   │  StripeWebhook · Subscription · Company               │     │
│   └───────────────────────────────────────────────────────┘     │
│                                                                  │
│   ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│   │   SERVICES   │  │    STATE     │  │   SECURITY   │         │
│   │              │  │  PROCESSORS  │  │              │         │
│   │ FacturX Gen  │  │  Send        │  │ InvoiceVoter │         │
│   │ UBL Gen      │  │  Cancel      │  │ ClientVoter  │         │
│   │ CII Gen      │  │  Pay         │  │ OwnerExt     │         │
│   │ PDF Gen      │  │              │  │              │         │
│   │ Archiver     │  └──────────────┘  └──────────────┘         │
│   │ NumberGen    │                                               │
│   │ Validator    │  ┌──────────────┐  ┌──────────────┐         │
│   │ StateMachine │  │  MESSENGER   │  │    STRIPE    │         │
│   │ AuditTrail   │  │  (async)     │  │              │         │
│   │ QuotaChecker │  │  PDP transmit│  │ Subscriptions│         │
│   │ ChorusClient │  │  PDF generate│  │ Webhooks     │         │
│   └──────────────┘  └──────────────┘  └──────────────┘         │
│                                                                  │
│   ┌──────────────────────────────────────────────────────┐      │
│   │                    ENTITIES                           │      │
│   │  User · Company · Client · Product · Invoice         │      │
│   │  InvoiceLine · InvoiceEvent · Subscription           │      │
│   └──────────────────────────────────────────────────────┘      │
└──────────────────────┬──────────────────┬───────────────────────┘
                       │                  │
              ┌────────┴───────┐  ┌───────┴────────┐
              │  PostgreSQL 16 │  │   Redis 7      │
              │  (données)     │  │  (Messenger)   │
              └────────────────┘  └────────────────┘
                                          │
                       ┌──────────────────┼──────────────────┐
                       │                  │                  │
              ┌────────┴───────┐ ┌────────┴──────┐ ┌────────┴──────┐
              │  Scaleway S3   │ │  Chorus Pro   │ │    Stripe     │
              │  (archivage)   │ │  (PDP)        │ │  (paiements)  │
              └────────────────┘ └───────────────┘ └───────────────┘
```

---

## 🚀 Démarrage rapide

### Prérequis

- **Docker** & **Docker Compose**
- **PHP 8.3** + Composer (pour le développement local)
- **Node.js 22** + npm (pour le frontend)
- **PostgreSQL 16** (via Docker ou local)

### Installation

```bash
# 1. Cloner le projet
git clone https://github.com/pierrearthur-demengel/factura.git
cd factura

# 2. Installer les dépendances et configurer les hooks Git
make install

# 3. Lancer l'environnement Docker
docker compose up -d

# 4. Créer la base de données et exécuter les migrations
cd backend
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Générer les clés JWT
php bin/console lexik:jwt:generate-keypair

# 6. Lancer le frontend
cd ../frontend
npm run dev
```

### Accès

| Service | URL | Description |
|---|---|---|
| 🖥️ Frontend | [localhost:5173](http://localhost:5173) | Interface React |
| 🔌 API | [localhost:8080/api](http://localhost:8080/api) | Documentation API Platform |
| 🗄️ PostgreSQL | `localhost:5432` | Base de données |
| 📦 Redis | `localhost:6379` | Queue Messenger |

### Variables d'environnement

```bash
# backend/.env.local (créer ce fichier, jamais versionné)

# Base de données
DATABASE_URL="postgresql://factura:factura@localhost:5432/factura"

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-passphrase

# Chorus Pro (PISTE OAuth2)
CHORUS_PRO_OAUTH_URL=https://sandbox-oauth.piste.gouv.fr/api/oauth/token
CHORUS_PRO_BASE_URL=https://sandbox-api.piste.gouv.fr
CHORUS_PRO_CLIENT_ID=change_me
CHORUS_PRO_CLIENT_SECRET=change_me

# Scaleway S3 (archivage légal)
S3_BUCKET=factura-archives
S3_REGION=fr-par
S3_ENDPOINT=https://s3.fr-par.scw.cloud
S3_KEY=change_me
S3_SECRET=change_me

# Stripe
STRIPE_SECRET_KEY=sk_test_change_me
STRIPE_WEBHOOK_SECRET=whsec_change_me
```

---

## 📖 API REST

L'API est documentée via **OpenAPI 3.1** et accessible via API Platform.

### Authentification

```bash
# Inscription
curl -X POST http://localhost:8080/api/register \
  -H "Content-Type: application/ld+json" \
  -d '{"email":"user@example.com","password":"S3cur3!","firstName":"Jean","lastName":"Dupont","companyName":"Ma Société","siren":"443061841"}'

# Connexion (récupère le JWT)
curl -X POST http://localhost:8080/api/auth \
  -H "Content-Type: application/ld+json" \
  -d '{"email":"user@example.com","password":"S3cur3!"}'
# → {"token": "eyJ..."}
```

### Endpoints principaux

| Méthode | Endpoint | Description |
|---|---|---|
| `POST` | `/api/register` | Inscription |
| `POST` | `/api/auth` | Connexion JWT |
| | | |
| `GET` | `/api/invoices` | Liste des factures (filtres, tri, pagination) |
| `POST` | `/api/invoices` | Créer une facture |
| `GET` | `/api/invoices/{id}` | Détail d'une facture |
| `PUT` | `/api/invoices/{id}` | Modifier une facture (DRAFT uniquement) |
| `DELETE` | `/api/invoices/{id}` | Supprimer une facture (DRAFT uniquement) |
| | | |
| `POST` | `/api/invoices/{id}/send` | Émettre la facture (DRAFT → SENT) |
| `POST` | `/api/invoices/{id}/cancel` | Annuler la facture |
| `POST` | `/api/invoices/{id}/pay` | Marquer comme payée |
| | | |
| `GET` | `/api/invoices/{id}/pdf` | Télécharger le Factur-X (PDF/A-3) |
| `GET` | `/api/invoices/{id}/download/facturx` | Télécharger le XML CII D16B |
| `GET` | `/api/invoices/{id}/download/ubl` | Télécharger le XML UBL 2.1 |
| `GET` | `/api/invoices/{id}/events` | Piste d'audit fiable (timeline) |
| | | |
| `GET` | `/api/clients` | Liste des clients |
| `POST` | `/api/clients` | Créer un client |
| `GET` | `/api/companies/me` | Mon entreprise |
| `PUT` | `/api/companies/{id}` | Modifier mon entreprise |
| `GET` | `/api/products` | Liste des produits |
| `POST` | `/api/products` | Créer un produit |
| | | |
| `GET` | `/api/subscription/portal` | URL portail Stripe |
| `POST` | `/webhooks/stripe` | Webhook Stripe |
| `POST` | `/webhooks/pdp/chorus-pro` | Callback Chorus Pro |

### Exemple : créer et émettre une facture

```bash
TOKEN="eyJ..."

# 1. Créer un client
curl -X POST http://localhost:8080/api/clients \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/ld+json" \
  -d '{
    "name": "Acme Corp",
    "siren": "732829320",
    "addressLine1": "42 rue de la Paix",
    "postalCode": "75002",
    "city": "Paris",
    "countryCode": "FR"
  }'

# 2. Créer la facture
curl -X POST http://localhost:8080/api/invoices \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/ld+json" \
  -d '{
    "buyer": "/api/clients/<uuid>",
    "issueDate": "2026-09-01",
    "dueDate": "2026-10-01",
    "paymentTerms": "Paiement à 30 jours fin de mois",
    "lines": [
      {
        "position": 1,
        "description": "Développement application web",
        "quantity": "10",
        "unit": "DAY",
        "unitPriceExcludingTax": "600.00",
        "vatRate": "20"
      }
    ]
  }'

# 3. Émettre (DRAFT → SENT, génère le numéro FA-2026-0001)
curl -X POST http://localhost:8080/api/invoices/<uuid>/send \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/ld+json"

# 4. Télécharger le PDF Factur-X
curl -o facture.pdf http://localhost:8080/api/invoices/<uuid>/pdf \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/pdf"
```

---

## 🧪 Tests & qualité

### Commandes

```bash
# Tests unitaires (82 tests, 122 assertions)
cd backend && vendor/bin/phpunit

# Analyse statique (PHPStan level 8 — zéro erreur)
cd backend && vendor/bin/phpstan analyse src --level=8

# Style de code (PHP CS Fixer)
cd backend && vendor/bin/php-cs-fixer fix --dry-run --diff

# Lint frontend (ESLint + TypeScript strict)
cd frontend && npm run lint

# Build production (TypeScript check inclus)
cd frontend && npm run build

# Tests E2E (Cypress)
cd frontend && npx cypress run
```

### Métriques

| Métrique | Valeur |
|---|---|
| Tests PHPUnit | **82** tests, **122** assertions |
| PHPStan | Level **8** — zéro erreur |
| PHP CS Fixer | Zéro violation |
| ESLint | Zéro erreur |
| TypeScript | `strict` mode — zéro erreur |
| Couverture | ≥ 80% sur `src/` |

### Couverture des tests

```
 Service/Format/FacturXGeneratorTest ..... 7 tests  ✅
 Service/Format/UblGeneratorTest ......... 4 tests  ✅
 Service/Format/CiiGeneratorTest ......... 4 tests  ✅
 Service/Invoice/InvoiceNumberGenTest .... 5 tests  ✅
 Service/Invoice/InvoiceValidatorTest .... 15 tests ✅
 Service/Invoice/InvoiceArchiverTest ..... 4 tests  ✅
 Service/Stripe/StripeWebhookHandlerTest . 5 tests  ✅
 Security/Voter/InvoiceVoterTest ......... 10 tests ✅
 Security/Voter/ClientVoterTest .......... 6 tests  ✅
 Validator/ValidSirenValidatorTest ....... 8 tests  ✅
 Validator/ValidIbanValidatorTest ........ 7 tests  ✅
 Entity/InvoiceTest ...................... 7 tests  ✅
```

---

## 🔄 Cycle de vie d'une facture

```
                    ┌─────────┐
                    │  DRAFT  │
                    └────┬────┘
                         │ send (génère FA-AAAA-NNNN)
                         │ + transmission PDP async
                         ▼
                    ┌─────────┐
              ┌─────│  SENT   │─────┐
              │     └────┬────┘     │
              │          │          │
         reject          │      acknowledge
              │          │          │
              ▼          │          ▼
        ┌──────────┐     │   ┌──────────────┐
        │ REJECTED │     │   │ ACKNOWLEDGED │
        └────┬─────┘     │   └──────┬───────┘
             │           │          │
          resend         │          │
             │        cancel        │
             └──► SENT ◄─┘     pay ─┤
                                    │
                              ┌─────┴────┐
                              │   PAID   │
                              └──────────┘

        ╔════════════╗
        ║ CANCELLED  ║ ◄── cancel (depuis DRAFT, SENT ou ACKNOWLEDGED)
        ╚════════════╝
```

Chaque transition est enregistrée dans le journal PAF (Piste d'Audit Fiable) via un `InvoiceEvent` immutable.

---

## 📄 Formats de facture

### Factur-X (recommandé)

Format hybride **PDF/A-3** avec XML CII D16B embarqué. Lisible par un humain (PDF) et par une machine (XML). Profil **EN 16931** conforme au standard européen.

```
facture.pdf
 ├── Rendu visuel PDF (FPDF)
 └── factur-x.xml (CII D16B embarqué)
      ├── CrossIndustryInvoice
      │   ├── ExchangedDocumentContext (profil EN16931)
      │   ├── ExchangedDocument (numéro, date, type 380)
      │   └── SupplyChainTradeTransaction
      │       ├── ApplicableHeaderTradeAgreement (vendeur, acheteur)
      │       ├── ApplicableHeaderTradeDelivery (livraison)
      │       └── ApplicableHeaderTradeSettlement (paiement, TVA, totaux)
      └── Signature SHA-256
```

### UBL 2.1

Format XML pur conforme à **Peppol BIS Billing 3.0**, pour l'interopérabilité européenne.

### CII D16B

Format XML pur **UN/CEFACT**, alternative au UBL. Même sémantique que le XML embarqué dans Factur-X.

---

## 🏛️ Conformité DGFiP

Ma Facture Pro implémente **toutes les exigences** du Décret n° 2022-1299 du 7 octobre 2022 :

| Exigence | Implémentation |
|---|---|
| Formats obligatoires | ✅ Factur-X, UBL 2.1, CII D16B |
| Profil EN 16931 | ✅ Via horstoeko/zugferd |
| Données vendeur | ✅ Raison sociale, SIREN, TVA, forme juridique, NAF, adresse |
| Données acheteur | ✅ Raison sociale, SIREN (si assujetti), adresse |
| Numérotation séquentielle | ✅ FA-AAAA-NNNN, verrou BDD, pas de trous |
| Mentions légales | ✅ Autoliquidation, exonération, art. 293B CGI |
| Piste d'audit fiable | ✅ InvoiceEvent immutable, SHA-256, archivage 10 ans |
| Raccordement PDP | ✅ Chorus Pro via PISTE OAuth2 |
| Immuabilité post-émission | ✅ Voter bloque toute modification après SENT |
| Archivage légal | ✅ S3 France, versioning, rétention 10 ans |

### Dossier de candidature

Le dossier complet se trouve dans `docs/` :

```
docs/
├── dossier-dgfip.md              # Dossier de candidature (7 sections)
├── api-openapi.json              # Spécification OpenAPI 3.1
├── api-openapi.yaml              # Spécification OpenAPI (YAML)
└── exemples-factures/
    ├── facture-simple.xml         # CII D16B — facture mono-ligne
    ├── facture-multi-lignes.xml   # CII D16B — facture multi-lignes
    ├── facture-exoneration.xml    # CII D16B — exonération TVA
    ├── facture-ubl-simple.xml     # UBL 2.1 — facture standard
    └── facture-ubl-avoir.xml     # UBL 2.1 — avoir (type 381)
```

---

## 📂 Structure du projet

```
ma-facture-pro/
├── backend/                          # Symfony 7.2 + API Platform 3.3
│   ├── src/
│   │   ├── Controller/               # 7 controllers (Auth, Export, Events, PDP, Stripe, Subscription, Company)
│   │   ├── Entity/                   # 8 entités Doctrine (UUID, pas d'auto-increment)
│   │   ├── Service/
│   │   │   ├── Format/               # FacturXGenerator, UblGenerator, CiiGenerator, PdfGenerator, Reader
│   │   │   ├── Invoice/              # NumberGenerator, Validator, StateMachine, AuditTrail, Archiver, QuotaChecker
│   │   │   ├── Pdp/                  # ChorusProClient, NullPdpClient, PdpDispatcher, PdpClientInterface
│   │   │   └── Stripe/               # SubscriptionManager, StripeWebhookHandler
│   │   ├── State/                    # InvoiceSendProcessor, CancelProcessor, PayProcessor
│   │   ├── Security/Voter/           # InvoiceVoter, ClientVoter
│   │   ├── Doctrine/                 # InvoiceOwnerExtension (multi-tenant)
│   │   ├── Exception/               # 5 exceptions métier spécifiques
│   │   ├── Message/                  # Messenger : TransmitInvoiceToPdp
│   │   └── Validator/               # ValidSiren, ValidIban
│   ├── config/
│   │   └── packages/
│   │       ├── workflow.yaml         # State machine facture (6 états, 6 transitions)
│   │       ├── security.yaml         # JWT + firewalls
│   │       ├── messenger.yaml        # Transport async + retry
│   │       └── ...                   # 14 fichiers de configuration
│   ├── tests/                        # 12 classes de test, 82 tests
│   ├── composer.json                 # 34 packages
│   └── phpstan.neon                  # PHPStan level 8
│
├── frontend/                         # React 18 + TypeScript + Vite
│   ├── src/
│   │   ├── pages/                    # 9 pages (Dashboard, Invoices, Clients, Settings, Auth, Landing)
│   │   ├── context/                  # AuthContext (JWT, refresh token)
│   │   ├── api/                      # Client API typé (factura.ts)
│   │   ├── App.tsx                   # Router + layout + protection des routes
│   │   └── main.tsx                  # Point d'entrée Vite
│   ├── cypress/
│   │   └── e2e/                      # Tests E2E (auth, invoices)
│   └── package.json
│
├── docs/                             # Documentation DGFiP + OpenAPI + exemples
├── hooks/                            # Git hooks (commit-msg, pre-push)
├── .github/workflows/                # CI/CD (tests, lint, build, deploy)
├── docker-compose.yml                # PHP 8.3, PostgreSQL 16, Nginx, Redis
├── Makefile                          # install, test, lint, fix, deploy
├── CHANGELOG.md                      # Keep a Changelog
└── LICENSE                           # MIT
```

---

## 📦 Déploiement

### Infrastructure recommandée

| Couche | Service | Coût | Région |
|---|---|---|---|
| 🖥️ Backend | [Fly.io](https://fly.io) | ~5-15 €/mois | Paris (CDG) |
| 🌐 Frontend | [Vercel](https://vercel.com) | Gratuit | CDN mondial |
| 🗄️ Base de données | Fly.io Postgres | ~0-10 €/mois | Paris |
| 📦 Storage S3 | [Scaleway](https://www.scaleway.com) | ~2 €/mois | Paris 🇫🇷 |
| 📮 Queue | [Upstash Redis](https://upstash.com) | Gratuit | Paris |

### Déployer

```bash
# Backend (Fly.io)
cd backend && fly deploy

# Frontend (Vercel)
cd frontend && vercel deploy --prod

# Ou tout en une commande
make deploy
```

### Secrets de production

```bash
# Fly.io
fly secrets set DATABASE_URL="postgresql://..." \
  JWT_PASSPHRASE="..." \
  STRIPE_SECRET_KEY="sk_live_..." \
  STRIPE_WEBHOOK_SECRET="whsec_..." \
  S3_KEY="..." \
  S3_SECRET="..." \
  CHORUS_PRO_CLIENT_ID="..." \
  CHORUS_PRO_CLIENT_SECRET="..."

# Vercel
vercel env add VITE_API_URL production
# → https://api.ma-facture-pro.com/api
```

---

## 🛠️ Stack technique

### Backend

| Package | Version | Rôle |
|---|---|---|
| `symfony/framework-bundle` | 7.2 | Framework PHP |
| `api-platform/core` | 3.3 | API REST + documentation |
| `doctrine/orm` | 3.x | ORM + migrations |
| `lexik/jwt-authentication-bundle` | 3.1 | Auth JWT RS256 |
| `gesdinet/jwt-refresh-token-bundle` | 1.3 | Refresh token |
| `symfony/workflow` | 7.2 | Machine à états facture |
| `symfony/messenger` | 7.2 | Queue async (PDP, PDF) |
| `horstoeko/zugferd` | 1.0 | Factur-X / CII D16B |
| `horstoeko/zugferdublbridge` | 1.0 | Conversion CII ↔ UBL |
| `stripe/stripe-php` | 20.x | Paiements & abonnements |
| `symfony/http-client` | 7.2 | Client HTTP (Chorus Pro) |

### Frontend

| Package | Version | Rôle |
|---|---|---|
| `react` | 18 | UI components |
| `react-router-dom` | 7.x | Routing SPA |
| `axios` | 1.x | Client HTTP |
| `typescript` | 5.x | Typage strict |
| `vite` | 8.x | Bundler |
| `cypress` | 14.x | Tests E2E |

### Qualité

| Outil | Niveau | Rôle |
|---|---|---|
| `phpstan` | **Level 8** | Analyse statique maximale |
| `php-cs-fixer` | PSR-12 | Style de code |
| `phpunit` | 11 | Tests unitaires |
| `eslint` | strict | Lint TypeScript/React |
| `cypress` | — | Tests end-to-end |

---

## 💰 Plans tarifaires

| | Gratuit | Pro | Équipe |
|---|:---:|:---:|:---:|
| **Prix** | **0 €**/mois | **12 €**/mois | **29 €**/mois |
| Factures/mois | 30 | ∞ | ∞ |
| Utilisateurs | 1 | 3 | 10 |
| Factur-X + UBL | ✅ | ✅ | ✅ |
| Émission Chorus Pro | ✅ | ✅ | ✅ |
| Archivage 10 ans | ✅ | ✅ | ✅ |
| Support prioritaire | — | ✅ | ✅ |
| API illimitée | — | — | ✅ |
| Export FEC comptable | — | — | ✅ |

---

## 📋 Makefile

```bash
make install   # Installe les dépendances + configure les hooks Git
make test      # Lance les tests PHPUnit
make lint      # PHPStan level 8 + PHP CS Fixer (dry-run)
make fix       # Applique les corrections PHP CS Fixer
make deploy    # Déploie backend (Fly.io) + frontend (Vercel)
```

---

## 🗓️ Roadmap

- [x] **v0.1** — Setup + entités + API Platform CRUD
- [x] **v0.2** — Validation DGFiP + numérotation séquentielle
- [x] **v0.3** — Workflow : cycle de vie de la facture
- [x] **v0.4** — Génération Factur-X + UBL + CII
- [x] **v0.5** — Archivage S3 + piste d'audit fiable
- [x] **v0.6** — Intégration PDP Chorus Pro
- [x] **v0.7** — Stripe abonnements + quotas
- [x] **v0.8** — Frontend React complet
- [x] **v1.0** — Label DGFiP + lancement public
- [ ] **v1.1** — Onboarding wizard + score conformité temps réel
- [ ] **v1.2** — Portail client (lien unique, paiement en ligne)
- [ ] **v1.3** — Relances automatiques (J-3, J+1, J+7, J+30)
- [ ] **v1.4** — Devis convertible en facture
- [ ] **v1.5** — Factures récurrentes
- [ ] **v1.6** — Export FEC (Fichier des Écritures Comptables)
- [ ] **v1.7** — Dashboard trésorerie (encaissements prévisionnels)
- [ ] **v1.8** — RGPD : export, suppression, portabilité des données

---

## 📝 Licence

[MIT](LICENSE) — © 2026 Pierre-Arthur Demengel

---

<div align="center">

**Ma Facture Pro** est un projet français, conçu pour la conformité et la simplicité.

Développé avec ❤️ à Paris.

</div>
