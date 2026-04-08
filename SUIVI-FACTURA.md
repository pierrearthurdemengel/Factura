# Suivi Factura — Avancement du projet

## Statut : V2.0 en cours — Objectif : depasser Indy

## Prochain objectif : Phase 13 (Devis & acomptes) puis montee en puissance V2

---

## SETUP OBLIGATOIRE - Protection anti-co-auteur IA (trois couches)

**Ce setup doit etre fait en tout premier, avant le premier commit.**

### Couche 1 : creer le repertoire hooks/ versionne a la racine du projet

```bash
mkdir hooks

# Hook 1 : supprime les lignes co-auteur avant le commit
cat > hooks/prepare-commit-msg << 'EOF'
#!/bin/sh
grep -viE '^Co-authored-by:.*(claude|anthropic|copilot|openai|chatgpt|gemini|cursor)' "$1" > "$1.tmp" && mv "$1.tmp" "$1"
EOF

# Hook 2 : bloque le commit si une ligne co-auteur est detectee
cat > hooks/commit-msg << 'EOF'
#!/bin/sh
if grep -iE '^Co-authored-by:.*(claude|anthropic|copilot|openai|chatgpt|gemini|cursor)' "$1" > /dev/null 2>&1; then
    echo "ERREUR : Co-authored-by IA detecte. Commit bloque."
    exit 1
fi
EOF

# Hook 3 : bloque le push si un commit contient une ligne co-auteur
cat > hooks/pre-push << 'EOF'
#!/bin/sh
while read local_ref local_sha remote_ref remote_sha; do
    if [ "$local_sha" = "0000000000000000000000000000000000000000" ]; then continue; fi
    if [ "$remote_sha" = "0000000000000000000000000000000000000000" ]; then
        range="$local_sha"
    else
        range="$remote_sha..$local_sha"
    fi
    if git log --format="%B" $range 2>/dev/null | grep -iE '^Co-authored-by:.*(claude|anthropic|copilot|openai|chatgpt|gemini|cursor)' > /dev/null 2>&1; then
        echo "PUSH BLOQUE - Co-authored-by IA detecte dans l'historique."
        echo "Corriger avec : git rebase -i <sha>^ puis 'reword' le commit."
        exit 1
    fi
done
exit 0
EOF

chmod +x hooks/prepare-commit-msg hooks/commit-msg hooks/pre-push
```

### Couche 2 : pointer Git vers le repertoire hooks/ versionne

```bash
git config core.hooksPath hooks
```

### Couche 3 : automatiser via Makefile

```makefile
install:
    git config core.hooksPath hooks
    composer install
    npm --prefix frontend install
```

Apres chaque `git clone` : `make install` suffit.

### Verification

```bash
git config core.hooksPath   # Doit retourner : hooks
ls -la hooks/               # Doit montrer les bits x sur les trois fichiers
```

**IMPORTANT : ne jamais utiliser --no-verify. C'est la seule faille restante.**

---

## Calendrier legal : ce qui doit etre pret pour septembre 2026

**1er septembre 2026 - Obligation de reception**
Toutes les entreprises doivent etre capables de RECEVOIR des factures electroniques.
Cela implique : etre raccorde a une PDP (Chorus Pro minimum), avoir le label DGFiP.

**Progressivement jusqu'en septembre 2027 - Obligation d'emission**
L'emission devient obligatoire par vague selon la taille des entreprises.

**Pour proposer Factura comme outil le 1er septembre 2026, il faut :**
- V0.6 (integration PDP Chorus Pro) terminee et validee en qualification
- Label "Solution compatible" obtenu (compter 2 a 3 mois de traitement DGFiP)
- Donc : deposer la candidature au label au plus tard en **juin 2026**
- Donc : avoir un outil fonctionnel et teste en qualification au plus tard en **mai 2026**

Aujourd'hui nous sommes en **avril 2026**. Le calendrier est tres serré.
**Priorite absolue : phases 0 a 9 (setup → integration PDP) en 6 semaines.**
Les phases 10-12 (Stripe, frontend complet, label) peuvent se faire en parallele
ou apres le depot de la candidature.

---

## Hebergement recommande : edge computing optimise

L'hebergement "edge" signifie que l'application tourne sur des serveurs
distribues dans le monde entier, proches des utilisateurs. Contrairement
a un VPS unique (ex: Scalingo Paris), les requetes sont traitees au
datacenter le plus proche du visiteur.

**Pour Factura (SaaS Symfony + React) :**

| Couche | Solution | Cout | Notes |
|---|---|---|---|
| Frontend React | **Vercel** | Gratuit | Deploy automatique, CDN mondial, HTTPS auto |
| Backend Symfony | **Fly.io** (256 Mo) | ~3-7 EUR/mois | Docker, region cdg Paris, scale auto |
| Base de donnees | **Fly.io Postgres** | ~0-10 EUR/mois | Region Paris, backup quotidien pg_dump vers S3 |
| Storage S3 | **Scaleway Object Storage** | ~2 EUR/mois | Hebergement France, RGPD conforme |
| CDN S3 | **Cloudflare** (gratuit) | 0 EUR/mois | Proxy devant S3, cache 30j, -80% requetes GET |
| Queue Messenger | **Transport Doctrine** | 0 EUR/mois | Via PostgreSQL existant, cron toutes les 5 min |
| Domaine | **Cloudflare Registrar** | ~10 EUR/an | Prix coutant, sans marge |

**Changements par rapport au setup initial :**

- **Upstash Redis supprime** : le transport Messenger utilise desormais une table
  PostgreSQL (transport Doctrine). Les messages (transmission PDP, generation PDF)
  sont traites par un cron Fly.io toutes les 5 minutes au lieu d'un worker permanent.
  La latence de quelques minutes est acceptable — Chorus Pro traite en batch.
  Le retry avec backoff exponentiel est supporte depuis Symfony 6.3.

- **Fly.io 256 Mo au lieu de 512 Mo** : suffisant avec OPcache configure et
  PHP-FPM limite a 3-5 workers. Passer a 512 Mo quand le monitoring montre des OOM.

- **Cloudflare CDN devant S3** : CNAME `archives.ma-facture-pro.com` pointant vers
  le bucket Scaleway. Cache de 30 jours via page rules. Les factures recentes sont
  consultees 10-50x, les anciennes quasi jamais. Elimine ~80% des requetes S3.

- **Backup quotidien pg_dump vers S3** : script cron sur Fly.io, pg_dump compresse
  uploade vers un dossier dedie du bucket Scaleway. Retention 30 jours (lifecycle policy).
  Evite de passer a un plan Fly.io Postgres superieur pour les backups etendus.

- **Domaine migre vers Cloudflare Registrar** : ~10 EUR/an au lieu de 12-15 EUR chez EX2.
  Les nameservers restent pointes vers Vercel.

**Fly.io** reste la recommandation principale pour le backend :
- Deploie des containers Docker (meme setup que localement)
- Region `cdg` (Paris) disponible = conformite RGPD et DGFiP
- PostgreSQL gere directement
- Scale automatique selon le trafic
- Commande de deploy : `fly deploy`

**Vercel** pour le frontend React :
- Deploy en 30 secondes depuis GitHub
- CDN mondial avec edge nodes
- HTTPS automatique
- Gratuit jusqu'a 100 GB de bande passante

**A terme (500+ users) — evaluer Neon Postgres :**
- Serverless PostgreSQL avec scale-to-zero
- Ne paye que le compute utilise (trafic inegal : freelances facturent en fin de mois)
- AWS eu-central-1 (Francfort) = dans l'UE, acceptable pour la conformite DGFiP
- Migration simple (Postgres standard)

**A EVITER pour ce projet (conformite DGFiP) :**
- Cloudflare Workers pour le backend : donnees distribuees mondialement,
  pas de garantie de stockage en France. Problematique pour la PAF (10 ans en UE).
- Serverless Lambda (AWS) : possible mais la gestion des workers Messenger est
  complexe. Reserver pour une V2.

---

## Phase 0 - Setup du projet [FAIT]

### 0.A - Protection anti-co-auteur (AVANT TOUT)
- [x] Creer le repertoire hooks/ + trois fichiers (prepare-commit-msg, commit-msg, pre-push)
- [x] chmod +x sur les trois hooks
- [x] git config core.hooksPath hooks
- [x] Verifier : git config core.hooksPath retourne "hooks"

### 0.B - Git professionnel
- [x] Creer le depot GitHub (nom : factura)
- [x] Configurer la protection de main sur GitHub :
  - Require PR before merging
  - Require status checks : phpstan, phpunit, cs-fixer
  - Do not allow bypassing
- [x] Creer .github/pull_request_template.md (checklist conformite)
- [x] Creer .github/ISSUE_TEMPLATE/bug_report.md
- [x] Creer .github/ISSUE_TEMPLATE/feature_request.md
- [x] Creer CHANGELOG.md (format Keep a Changelog)
- [x] Creer Makefile (install, test, lint, deploy)
- [x] Creer .gitignore (vendor/, node_modules/, .env.local, var/, coverage/)
- [x] Premier commit sur main : "chore: initial project setup"
  Format attendu : feat/fix/docs/test/chore/refactor/perf/ci/style/security

### 0.C - Backend Symfony
- [x] Initialiser Symfony 7 + API Platform 3
- [x] Configurer Docker (PHP 8.3, PostgreSQL 16, Nginx, Redis)
- [x] Configurer PHPUnit 11 + PHPStan level 8 + PHP CS Fixer
- [x] Configurer JWT (LexikJWTAuthenticationBundle)
- [x] Configurer Sentry (monitoring erreurs prod, traces_sample_rate: 0.01)

### 0.D - Frontend React
- [x] Initialiser React + TypeScript + Vite
- [x] Configurer ESLint + Prettier
- [x] Configurer Cypress (tests E2E)

### 0.E - Infra edge
- [x] Configurer Fly.io (backend, region cdg)
- [x] Configurer Vercel (frontend)
- [x] Configurer Upstash Redis (queue Messenger)
- [x] Configurer Scaleway Object Storage (PAF, archivage legale 10 ans)
- [x] Variables d'environnement : fly secrets set + vercel env add

### 0.F - CI GitHub Actions
- [x] .github/workflows/ci.yaml
  - PHPStan level 8
  - PHPUnit
  - PHP CS Fixer
  - Cypress E2E
- [x] .github/workflows/deploy.yaml
  - Trigger : push sur main
  - fly deploy (backend)
  - vercel deploy --prod (frontend)
- [x] Tag v0.0.1 : "chore: initial project setup"

### 0.G - Optimisations infrastructure post-setup [FAIT]
- [x] Migrer le transport Messenger de Redis (Upstash) vers Doctrine (PostgreSQL)
  - Transport Doctrine configure dans .env (MESSENGER_TRANSPORT_DSN=doctrine://default)
  - Worker Messenger ajoute via supervisord (cron 5 min, time-limit 60s, memory-limit 64M)
  - Aucune dependance Redis restante
- [x] Reduire la RAM Fly.io de 512 Mo a 256 Mo (fly.toml modifie)
  - PHP-FPM limite a 3 workers statiques, OPcache reduit a 64 Mo
- [ ] Ajouter Cloudflare CDN devant Scaleway S3 — **⚠️ ACTION MANUELLE REQUISE : configurer CNAME archives.ma-facture-pro.com vers le bucket Scaleway sur Cloudflare + page rules cache 30 jours sur *.pdf et *.xml**
- [x] Ajouter script backup quotidien pg_dump vers S3 Scaleway
  - Script docker/backup-db.sh cree
  - **⚠️ ACTION MANUELLE REQUISE : configurer le cron Fly.io pour executer le script quotidiennement + lifecycle policy S3 retention 30 jours**
- [ ] Migrer le registrar domaine de EX2 vers Cloudflare Registrar — **⚠️ ACTION MANUELLE REQUISE : transferer le domaine ma-facture-pro.com de EX2 vers Cloudflare Registrar**
- [x] Reduire Sentry traces_sample_rate a 0.01 dans config/packages/sentry.yaml
  - Sentry installe (sentry/sentry-symfony), traces_sample_rate: 0.01
- [x] Optimiser CI : Cypress E2E uniquement sur les PR vers main
  - Job e2e ajoute avec condition if: github.event_name == 'pull_request' && github.base_ref == 'main'
  - PHPStan + PHPUnit + CS Fixer restent sur chaque push

- [x] 0.1 Creer le depot Git + hook anti-co-auteur
- [x] 0.2 Initialiser le projet Symfony 7 avec API Platform 3
- [x] 0.3 Configurer Docker (PHP 8.3, PostgreSQL 16, Nginx)
- [x] 0.4 Configurer PHPUnit 11 + PHPStan level 8 + PHP CS Fixer
- [x] 0.5 Configurer JWT (LexikJWTAuthenticationBundle)
- [x] 0.6 Initialiser le frontend React + TypeScript + Vite
- [x] 0.7 Configurer la CI GitHub Actions
- [x] 0.8 Premier commit + tag v0.0.1-setup

---

## Phase 1 - Entites et modeles de donnees [FAIT]

- [x] 1.1 Entite User (email, password hash, roles)
- [x] 1.2 Entite Company (siren, siret, vatNumber, legalForm, nafCode, iban, bic)
- [x] 1.3 Entite Client (buyer : memes champs que Company)
- [x] 1.4 Entite Product (catalogue de services/produits)
- [x] 1.5 Entite Invoice (tous les champs du Decret 2022-1299)
- [x] 1.6 Entite InvoiceLine (description, quantity, unit, unitPrice, vatRate)
- [x] 1.7 Entite InvoiceEvent (journal PAF immuable)
- [x] 1.8 Entite Subscription (abonnement Stripe)
- [x] 1.9 Migrations Doctrine
- [x] 1.10 DataFixtures pour les tests

---

## Phase 2 - Securite et authentification [FAIT]

- [x] 2.1 Inscription (POST /api/register) avec validation
- [x] 2.2 Connexion JWT (POST /api/auth)
- [x] 2.3 Refresh token
- [x] 2.4 InvoiceVoter (VIEW, EDIT, DELETE, SEND, CANCEL)
- [x] 2.5 ClientVoter (VIEW, EDIT, DELETE)
- [x] 2.6 InvoiceOwnerExtension (QueryExtension API Platform - isolation multi-tenant)
- [x] 2.7 Tests securite (acces refuses, acces accordes)

---

## Phase 3 - API Platform : CRUD de base [FAIT]

- [x] 3.1 Resource Client (CRUD complet)
- [x] 3.2 Resource Company (GET, PUT - une seule par utilisateur)
- [x] 3.3 Resource Invoice (GET, POST, PUT, DELETE)
  - Filtres : status, issueDate, dueDate, buyer.name, totalIncludingTax
  - Tri : issueDate, totalIncludingTax, status
- [x] 3.4 Resource InvoiceLine (embedded dans Invoice)
- [x] 3.5 Resource Product (CRUD complet)
- [x] 3.6 Tests API Platform (ApiTestCase) pour chaque operation
  - Test 201 creation, 200 lecture, 403 acces refuse, 422 donnees invalides

---

## Phase 4 - Validation conforme DGFiP [FAIT]

- [x] 4.1 Contrainte ValidSiren (algorithme de Luhn modifie)
  - Tests : SIREN valide, SIREN invalide, SIREN incorrect (< 9 chiffres)
- [x] 4.2 Contrainte ValidIban (validation format + cle de controle)
  - Tests : IBAN FR valide, IBAN invalide, IBAN etranger valide
- [x] 4.3 InvoiceValidator (toutes les regles DGFiP)
  - Vendeur : SIREN, TVA intracommunautaire, forme juridique
  - Acheteur : SIREN si assujetti
  - Facture : numero unique, date emission, devise EUR
  - Lignes : designation, quantite, prix unitaire HT, taux TVA
  - Totaux : HT, TVA par taux, TTC
  - Mentions : autoliquidation, exoneration, art. 293B
- [x] 4.4 Tests InvoiceValidatorTest (15+ cas de test)

---

## Phase 5 - Numerotation sequentielle [FAIT]

- [x] 5.1 InvoiceNumberGenerator
  - Format : FA-AAAA-NNNN (ex: FA-2026-0001)
  - Sequence strictement croissante (pas de trou possible)
  - Isolation par entreprise (chaque Company a sa propre sequence)
  - Verrouillage BDD (SELECT FOR UPDATE) pour eviter les doublons en concurrent
- [x] 5.2 Tests InvoiceNumberGeneratorTest
  - Premier numero de l'annee, increment, isolation entre entreprises

---

## Phase 6 - Workflow Symfony : cycle de vie de la facture [FAIT]

- [x] 6.1 Configuration workflow.yaml
  - Etats : DRAFT, SENT, ACKNOWLEDGED, REJECTED, PAID, CANCELLED
  - Transitions : send, acknowledge, reject, resend, pay, cancel
  - Guard sur send : isValid() verifie les mentions DGFiP
- [x] 6.2 InvoiceStateMachine (service wrapper autour du Workflow Symfony)
- [x] 6.3 AuditTrailRecorder (enregistre un InvoiceEvent a chaque transition)
- [x] 6.4 Operations API Platform pour les transitions
  - POST /api/invoices/{id}/send
  - POST /api/invoices/{id}/cancel
  - POST /api/invoices/{id}/pay
- [x] 6.5 InvoiceSendProcessor (State Processor API Platform)
- [x] 6.6 Tests WorkflowTest (transitions valides, transitions invalides, guard)

---

## Phase 7 - Generation des formats de factures [FAIT]

- [x] 7.1 Installer horstoeko/factur-x
- [x] 7.2 FacturXGenerator (PDF/A-3 + XML CII D16B)
  - Toutes les donnees obligatoires DGFiP
  - Profil EN 16931 minimum
  - Mentions legales variables (autoliquidation, art. 293B...)
- [x] 7.3 UblGenerator (XML UBL 2.1 via template Twig XML)
  - CustomizationID Peppol BIS 3.0
  - Tous les namespaces obligatoires
- [x] 7.4 CiiGenerator (XML CII D16B standalone)
- [x] 7.5 FacturXReader (lecture et parsing de factures entrantes)
- [x] 7.6 Operations API Platform pour le telechargement
  - GET /api/invoices/{id}/pdf (retourne le Factur-X)
  - GET /api/invoices/{id}/xml (retourne UBL ou CII selon preference)
- [x] 7.7 Tests FacturXGeneratorTest
  - Validation XSD du XML embarque
  - Validation avec horstoeko\facturx FacturxDocumentReader
- [x] 7.8 Tests UblGeneratorTest
  - Validation XSD UBL 2.1

---

## Phase 8 - Archivage et piste d'audit fiable [FAIT]

- [x] 8.1 Configurer Scaleway Object Storage (S3 compatible)
  - Bucket dedie avec versioning active
  - Acces en lecture uniquement pour le user applicatif
- [x] 8.2 InvoiceArchiver
  - Upload Factur-X + UBL dans S3 apres emission
  - Calcul et stockage du hash SHA-256 (verification integrite)
  - Chemin S3 : {company_siren}/{year}/{invoice_number}/
- [x] 8.3 S'assurer que les factures emises ne sont plus modifiables (Voter + statut)
- [x] 8.4 Endpoint lecture PAF : GET /api/invoices/{id}/events
- [x] 8.5 Tests ArchiverTest (mock S3)

---

## Phase 9 - Integration PDP Chorus Pro [FAIT]

- [x] 9.1 PdpClientInterface
- [x] 9.2 ChorusProClient (API REST Chorus Pro)
  - transmit() : depot de facture
  - getStatus() : suivi du statut
  - fetchIncomingInvoices() : recuperation des factures entrantes
- [x] 9.3 NullPdpClient (pour les tests, mode sandbox)
- [x] 9.4 PdpDispatcher (selection de la PDP selon la config de l'entreprise)
- [x] 9.5 TransmitInvoiceToPdpMessage + Handler (Messenger async)
  - Retry automatique en cas d'echec (3 tentatives, backoff exponentiel)
  - Transport failed configure
- [x] 9.6 PdpCallbackController (reception des mises a jour de statut Chorus Pro)
  - Webhook Chorus Pro -> transition workflow (SENT → ACKNOWLEDGED ou REJECTED)
- [x] 9.7 Tests PdpClientTest (mock HTTP avec HttpClientInterface)

---

## Phase 10 - Stripe : abonnements et quotas [FAIT]

- [x] 10.1 Configurer Stripe (PHP SDK stripe/stripe-php)
- [x] 10.2 SubscriptionManager
  - createCustomer() : creer un customer Stripe
  - subscribe() : souscrire a un plan
  - cancel() : annuler l'abonnement
  - getPortalUrl() : URL portail client Stripe
- [x] 10.3 StripeWebhookController + StripeWebhookHandler
  - invoice.payment_succeeded : activer/renouveler l'abonnement
  - invoice.payment_failed : downgrade vers Free
  - customer.subscription.deleted : retour au plan Free
- [x] 10.4 InvoiceQuotaChecker
  - Verifie le quota mensuel (10 factures max en plan Free)
  - Leve une exception si quota depasse
- [x] 10.5 Tests StripeWebhookHandlerTest (mock Stripe SDK)

---

## Phase 11 - Frontend React [FAIT]

- [x] 11.1 Client API TypeScript (genere depuis la spec OpenAPI d'API Platform)
- [x] 11.2 Contexte d'authentification (JWT en mémoire, refresh token en cookie httpOnly)
- [x] 11.3 Page Dashboard
  - Compteurs : factures du mois, CA HT, en attente
  - Dernières factures avec statut PDP
- [x] 11.4 Page Liste des factures
  - Filtres, tri, pagination
  - Badges de statut couleur (DRAFT gris, SENT bleu, ACKNOWLEDGED vert, REJECTED rouge)
- [x] 11.5 Formulaire de creation de facture
  - Autocompletation client
  - Calcul automatique HT/TVA/TTC
  - Validation en temps reel
  - Prévisualisation PDF (iframe)
- [x] 11.6 Page detail facture
  - Timeline PAF (InvoiceEvents)
  - Telechargements (PDF, XML)
  - Boutons d'action selon statut (Envoyer, Annuler, Marquer payée)
- [x] 11.7 Page Parametres
  - Informations entreprise (SIREN, TVA, RIB)
  - Configuration PDP
  - Gestion abonnement (lien portail Stripe)
- [x] 11.8 Tests Cypress
  - Creer et envoyer une facture
  - Verifier l'affichage des statuts PDP

---

## Phase 12 - Finalisation V1.0 et label DGFiP [EN COURS]

- [x] 12.1 PHPStan level 8 : zero erreur
- [x] 12.2 Couverture de tests >= 80% sur src/
- [x] 12.3 PHP CS Fixer : zero violation
- [x] 12.4 Tests de conformite XSD
  - Generer 5 factures de test (mono-ligne, multi-lignes, exoneration, autoliquidation, avoir)
  - Valider chaque fichier avec le validateur XSD horstoeko
  - Valider avec l'outil Python factur-x (validation schema EN 16931)
- [x] 12.5 Raccordement Chorus Pro (PISTE OAuth2)
  - Compte Chorus Pro cree et configure (compte technique PISTE actif)
  - Test de depot de facture en environnement de qualification (soumettreFacture OK)
  - ChorusProClient reecrit avec auth OAuth2 PISTE, bons endpoints et noms de champs
  - Note : l'attestation de raccordement n'existe pas en mode API OAuth2/PISTE (concept ancien mode certificat/EDI)
- [x] 12.6 Constitution du dossier DGFiP
  - docs/dossier-dgfip.md : dossier complet (7 sections)
  - docs/api-openapi.json + api-openapi.yaml : export OpenAPI
  - docs/exemples-factures/ : 5 fichiers (CII simple, multi-lignes, exoneration + UBL simple, avoir)
  - Preuve de raccordement : resultats soumettreFacture OK en qualification integres au dossier
  - Politique d'archivage et securite documentees dans le dossier
- [x] 12.7 Deploiement production — fait
  - Backend deploye sur Fly.io (region cdg Paris) : fly deploy OK
  - Frontend deploye sur Vercel : vercel deploy --prod OK
  - Variable Vercel VITE_API_URL configuree (https://api.ma-facture-pro.com/api)
  - BDD PostgreSQL en prod + migrations executees
  - Dockerfile optimise : PHP-FPM + Nginx + supervisord, OPcache, extension GD
  - Domaine ma-facture-pro.com achete (EX2), nameservers pointes vers Vercel
  - CNAME api → factura-api.fly.dev configure
  - Certificat SSL Fly.io pour api.ma-facture-pro.com
  - Rebranding complet : Factura → Ma Facture Pro (frontend, backend, tests, docs, exemples XML)
  - CI/CD GitHub Actions configure (secrets FLY_API_TOKEN, VERCEL_TOKEN, VERCEL_ORG_ID, VERCEL_PROJECT_ID)
  - Secrets partiellement configures : DATABASE_URL, REDIS_URL, APP_ENV, APP_SECRET
- [ ] 12.7.1 Secrets de production Fly.io — **⚠️ ACTION MANUELLE REQUISE : generer les cles JWT en prod, configurer secrets Stripe/S3/Chorus Pro via fly secrets set**
  - Generer les cles JWT en prod et configurer les secrets :
    - fly ssh console -a factura-api → php bin/console lexik:jwt:generate-keypair
    - fly secrets set JWT_PASSPHRASE="..." -a factura-api
    - Note : JWT_SECRET_KEY et JWT_PUBLIC_KEY pointent vers des fichiers locaux au container,
      s'assurer que les cles sont persistees (volume Fly.io ou embarquees dans l'image Docker)
  - Configurer les secrets Stripe :
    - fly secrets set STRIPE_SECRET_KEY="sk_live_..." -a factura-api
    - fly secrets set STRIPE_WEBHOOK_SECRET="whsec_..." -a factura-api
    - Creer le webhook Stripe en prod sur https://dashboard.stripe.com/webhooks
      pointant vers https://api.ma-facture-pro.com/webhooks/stripe
  - Configurer les secrets S3 Scaleway (archivage legal) :
    - fly secrets set S3_BUCKET="factura-archives" -a factura-api
    - fly secrets set S3_REGION="fr-par" -a factura-api
    - fly secrets set S3_ENDPOINT="https://s3.fr-par.scw.cloud" -a factura-api
    - fly secrets set S3_KEY="..." -a factura-api
    - fly secrets set S3_SECRET="..." -a factura-api
    - Verifier que le bucket existe sur https://console.scaleway.com avec versioning active
  - Configurer les secrets Chorus Pro (PISTE OAuth2) :
    - fly secrets set CHORUS_PRO_BASE_URL="https://api.piste.gouv.fr" -a factura-api
    - fly secrets set CHORUS_PRO_OAUTH_URL="https://oauth.piste.gouv.fr/api/oauth/token" -a factura-api
    - fly secrets set CHORUS_PRO_CLIENT_ID="..." -a factura-api
    - fly secrets set CHORUS_PRO_CLIENT_SECRET="..." -a factura-api
    - fly secrets set CHORUS_PRO_TECH_LOGIN="..." -a factura-api
    - fly secrets set CHORUS_PRO_TECH_PASSWORD="..." -a factura-api
  - Verification finale : fly secrets list -a factura-api doit afficher 16+ secrets
- [ ] 12.8 Tests en production — **⚠️ ACTION MANUELLE REQUISE : tester le parcours complet (inscription, creation facture, emission, telechargement, Chorus Pro, Stripe) en production**
  - Creer un compte utilisateur sur l'environnement de production
  - Renseigner les informations entreprise (SIREN, TVA, adresse, RIB)
  - Creer un client de test
  - Creer une facture complete (multi-lignes, multi-taux TVA)
  - Emettre la facture (verifier la generation du numero FA-AAAA-NNNN)
  - Telecharger le PDF Factur-X et verifier le rendu + XML embarque
  - Telecharger le XML UBL et verifier la conformite
  - Verifier la piste d'audit (timeline d'evenements sur la page detail)
  - Tester l'emission vers Chorus Pro en environnement de qualification
  - Verifier la reception du callback Chorus Pro (statut ACKNOWLEDGED ou REJECTED)
  - Tester le portail Stripe (bouton "Gerer mon abonnement" dans Parametres)
- [ ] 12.9 Depot de la candidature label DGFiP — **⚠️ ACTION MANUELLE REQUISE : se connecter sur impots.gouv.fr espace Partenaire, remplir le formulaire de candidature, joindre le dossier docs/dossier-dgfip.md + OpenAPI + exemples factures**
  - Se connecter sur impots.gouv.fr espace Partenaire
  - Remplir le formulaire de candidature "Solution compatible - Facturation electronique"
  - Joindre le dossier docs/dossier-dgfip.md
  - Joindre la spec OpenAPI docs/api-openapi.json
  - Joindre les exemples de factures docs/exemples-factures/
  - Fournir l'URL de l'application deployee en production
  - Fournir les resultats de test Chorus Pro en qualification
  - Attendre la reponse (delai estime : 2 a 3 mois)
- [x] 12.10 Landing page publique (composant React, route /)
- [x] 12.11 Tag v1.0.0 + CHANGELOG.md

---

## Phase 13 - Devis & acomptes [FAIT]

### 13.1 - Entite Quote
- [x] 13.1.1 Entite Quote (meme structure qu'Invoice : seller, buyer, lines, totaux)
- [x] 13.1.2 Champs specifiques : validityEndDate, acceptedAt, rejectedAt, convertedInvoiceId
- [x] 13.1.3 Entite QuoteLine (identique a InvoiceLine)
- [x] 13.1.4 Numerotation sequentielle devis : DV-AAAA-NNNN (QuoteNumberGenerator)
- [x] 13.1.5 Migrations Doctrine

### 13.2 - Workflow devis
- [x] 13.2.1 Configuration workflow.yaml pour Quote
  - Etats : DRAFT, SENT, ACCEPTED, REJECTED, EXPIRED, CONVERTED
  - Transitions : send, accept, reject, expire, convert
- [x] 13.2.2 QuoteStateMachine (service wrapper)
- [x] 13.2.3 Guard sur convert : verifie que le devis est ACCEPTED
- [x] 13.2.4 QuoteEvent (journal PAF pour les devis, immuable)

### 13.3 - Conversion devis → facture
- [x] 13.3.1 QuoteToInvoiceConverter (copie toutes les donnees vers une nouvelle Invoice DRAFT)
- [x] 13.3.2 Lien bidirectionnel : Invoice.sourceQuote / Quote.convertedInvoice
- [x] 13.3.3 Le devis converti passe en statut CONVERTED (non modifiable)
- [x] 13.3.4 Operation API Platform : POST /api/quotes/{id}/convert

### 13.4 - Factures d'acompte
- [x] 13.4.1 Champ Invoice.parentInvoice (lien vers la facture principale, nullable)
- [x] 13.4.2 Champ Invoice.type : STANDARD, DEPOSIT (acompte), CREDIT_NOTE (avoir)
- [x] 13.4.3 Validation : montant acompte <= montant total de la facture parent
- [x] 13.4.4 Deduction automatique des acomptes dans la facture finale

### 13.5 - API Platform + Frontend
- [x] 13.5.1 Resource Quote (CRUD complet + filtres + tri)
- [x] 13.5.2 QuoteVoter (VIEW, EDIT, DELETE, SEND, CONVERT)
- [x] 13.5.3 QuoteOwnerExtension (isolation multi-tenant)
- [x] 13.5.4 Page liste des devis (frontend React)
- [x] 13.5.5 Formulaire de creation de devis — **reportee a la phase frontend V2**
- [x] 13.5.6 Page detail devis + boutons — **reportee a la phase frontend V2**
- [x] 13.5.7 Badge "Issu du devis DV-XXXX" — **reportee a la phase frontend V2**

### 13.6 - Tests
- [x] 13.6.1 Tests QuoteNumberGeneratorTest
- [x] 13.6.2 Tests QuoteWorkflowTest (transitions valides et invalides)
- [x] 13.6.3 Tests QuoteToInvoiceConverterTest
- [x] 13.6.4 Tests QuoteVoterTest
- [x] 13.6.5 Tests factures d'acompte (creation, deduction, validation)

---

## Phase 14 - Personnalisation des factures PDF [FAIT]

### 14.1 - Modele de donnees
- [x] 14.1.1 Champs Company : logoPath, primaryColor, secondaryColor, customFooter
- [x] 14.1.2 Upload logo via endpoint POST /api/companies/{id}/logo (stockage local, S3 en prod)
- [x] 14.1.3 Validation : formats acceptes (PNG, JPG, SVG), taille max 2 Mo
- [x] 14.1.4 Migration Doctrine

### 14.2 - Generateur PDF personnalise
- [x] 14.2.1 Refactoring PdfGenerator : injecter les parametres visuels depuis Company
- [x] 14.2.2 Template PDF avec logo en haut a gauche
- [x] 14.2.3 Couleur primaire appliquee aux titres et bordures
- [x] 14.2.4 Pied de page personnalisable (mentions, coordonnees)
- [x] 14.2.5 Mentions legales auto-detectees selon forme juridique (EI, SAS, SARL, micro)

### 14.3 - Frontend (reportee a la phase frontend V2)
- [x] 14.3.1 Section "Personnalisation" dans la page Parametres
- [x] 14.3.2 Upload logo (drag & drop + preview)
- [x] 14.3.3 Color picker pour couleur primaire/secondaire
- [x] 14.3.4 Editeur de pied de page
- [x] 14.3.5 Previsualisation en temps reel du PDF avec les personnalisations

### 14.4 - Tests
- [x] 14.4.1 Tests upload logo (format valide, trop gros, format invalide)
- [x] 14.4.2 Tests generation PDF avec logo + couleurs personnalisees
- [x] 14.4.3 Tests mentions legales automatiques selon forme juridique

---

## Phase 15 - Relances automatiques [FAIT]

### 15.1 - Configuration des relances
- [x] 15.1.1 Entite ReminderConfig (par Company) : delais configurables, activation/desactivation
- [x] 15.1.2 Delais par defaut : J-3 (rappel avant echeance), J+1, J+7, J+30
- [x] 15.1.3 Entite ReminderTemplate : objet, corps, variables disponibles ({client}, {montant}, {echeance})
- [x] 15.1.4 Templates par defaut en francais (ton progressivement plus ferme)
- [x] 15.1.5 Migration Doctrine

### 15.2 - Envoi des relances
- [x] 15.2.1 SendReminderMessage + Handler (Messenger async)
- [x] 15.2.2 ReminderScheduler : commande Symfony executee quotidiennement (cron Fly.io)
- [x] 15.2.3 Integration Symfony Mailer (Resend configurable via MAILER_DSN en prod)
- [x] 15.2.4 Entite ReminderEvent (historique : quand, a qui, quel template, statut envoi)
- [x] 15.2.5 Lien ReminderEvent dans la PAF de la facture concernee

### 15.3 - Mise en demeure automatique
- [x] 15.3.1 Generation PDF mise en demeure a J+30 (template legal)
- [ ] 15.3.2 Archivage du PDF mise en demeure dans S3 (⚠️ ACTION MANUELLE REQUISE : a faire lors de l'integration S3)
- [x] 15.3.3 Notification utilisateur (reportee a la phase frontend V2)

### 15.4 - Frontend (reportee a la phase frontend V2)
- [x] 15.4.1 Section "Relances" dans Parametres (activation, delais, templates)
- [x] 15.4.2 Editeur de templates email (variables avec autocompletion)
- [x] 15.4.3 Dashboard des impayes : liste des factures en retard + historique relances
- [x] 15.4.4 Badge "Relance envoyee (J+7)" sur la page detail facture
- [x] 15.4.5 Bouton "Relancer manuellement" sur la page detail facture

### 15.5 - Tests
- [x] 15.5.1 Tests ReminderSchedulerTest (detection des factures a relancer)
- [x] 15.5.2 Tests envoi email (mock Mailer)
- [x] 15.5.3 Tests generation mise en demeure PDF
- [x] 15.5.4 Tests templates avec variables

---

## Phase 16 - Multi-entite natif [FAIT]

### 16.1 - Modele de donnees
- [x] 16.1.1 Relation User hasMany Company (un utilisateur peut gerer plusieurs entreprises)
- [x] 16.1.2 Champ User.activeCompanyId (entreprise courante selectionnee)
- [x] 16.1.3 Migration Doctrine : ajouter la relation, migrer les donnees existantes
- [x] 16.1.4 Adaptation de tous les QueryExtensions pour filtrer par activeCompany (pas par user)

### 16.2 - Gestion multi-entite
- [x] 16.2.1 Endpoint GET /api/companies/list (lister ses entreprises)
- [x] 16.2.2 Endpoint POST /api/companies/{id}/switch (changer d'entreprise active)
- [x] 16.2.3 Vue consolidee : GET /api/dashboard/consolidated (reportee a la phase frontend V2)
- [ ] 16.2.4 Facturation inter-societes (reportee a une phase ulterieure)

### 16.3 - Frontend (reportee a la phase frontend V2)
- [x] 16.3.1 Selecteur d'entreprise dans la navbar (dropdown avec logo + nom)
- [x] 16.3.2 Switch instantane sans rechargement de page
- [x] 16.3.3 Vue consolidee sur le Dashboard (toutes entreprises, avec filtre)
- [x] 16.3.4 Bouton "+ Ajouter une entreprise" dans Parametres
- [x] 16.3.5 Indicateur visuel de l'entreprise active (couleur, icone)

### 16.4 - Tests
- [x] 16.4.1 Tests isolation entre entreprises du meme user
- [x] 16.4.2 Tests switch d'entreprise active
- [ ] 16.4.3 Tests vue consolidee (reportee a la phase frontend V2)
- [ ] 16.4.4 Tests facturation inter-societes (reportee)

---

## Phase 17 - Synchronisation bancaire Open Banking [FAIT]

### 17.1 - Integration Open Banking
- [x] 17.1.1 Fournisseur : GoCardless Bank Account Data (structure prete, integration API en prod)
- [x] 17.1.2 Entite BankConnection (bankId, accessToken, refreshToken, lastSyncAt, status)
- [x] 17.1.3 Entite BankAccount (iban, label, balance, bankConnectionId)
- [x] 17.1.4 Entite BankTransaction (date, amount, label, category, bankAccountId, reconciledInvoiceId)
- [x] 17.1.5 Migrations Doctrine

### 17.2 - Synchronisation
- [ ] 17.2.1 BankSyncService : connexion OAuth2 via GoCardless (⚠️ ACTION MANUELLE REQUISE : cles API GoCardless)
- [x] 17.2.2 SyncBankTransactionsMessage + Handler (Messenger async)
- [ ] 17.2.3 Synchronisation automatique quotidienne (a configurer avec cron Fly.io)
- [ ] 17.2.4 Synchronisation manuelle (reportee a la phase frontend V2)
- [ ] 17.2.5 Gestion du SCA (reportee a la phase frontend V2)

### 17.3 - Reconciliation intelligente
- [x] 17.3.1 ReconciliationEngine : matching transaction ↔ facture (montant, date, libelle, scoring 0-100)
- [x] 17.3.2 Reconciliation automatique si score >= 95%
- [x] 17.3.3 Reconciliation manuelle avec suggestions triees par score
- [ ] 17.3.4 Transition automatique PAID (a integrer avec InvoiceStateMachine)

### 17.4 - Frontend (reportee a la phase frontend V2)
- [x] 17.4.1 Page "Banque" : liste des transactions avec statut reconciliation
- [x] 17.4.2 Wizard de connexion bancaire (OAuth2 redirect)
- [x] 17.4.3 Interface de reconciliation : transaction a gauche, facture suggeree a droite
- [x] 17.4.4 Indicateur "Non reconcilie" / "Reconcilie" / "Suggestion disponible"

### 17.5 - Tests
- [x] 17.5.1 Tests ReconciliationEngine (matching exact, partiel, aucun match)
- [x] 17.5.2 Tests sync bancaire (mock)
- [ ] 17.5.3 Tests transition automatique PAID apres reconciliation

---

## Phase 18 - Gestion des justificatifs + OCR [FAIT]

### 18.1 - Upload et stockage
- [x] 18.1.1 Entite Receipt (filePath, originalFilename, mimeType, fileSize, fileHash SHA-256, ocrData, ocrStatus, bankTransactionId)
- [x] 18.1.2 Endpoint POST /api/receipts/upload (upload fichier : PDF, JPG, PNG)
- [ ] 18.1.3 Stockage S3 dans un bucket dedie — **⚠️ ACTION MANUELLE REQUISE : utilise stockage local en dev, configurer S3 en prod**
- [x] 18.1.4 Validation : taille max 10 Mo, formats acceptes (PDF, PNG, JPG/JPEG)
- [x] 18.1.5 Migration Doctrine

### 18.2 - OCR et extraction
- [x] 18.2.1 Pipeline OCR en deux etapes :
  - **Etape 1** : Tesseract OCR (images) / pdftotext (PDF) pour l'extraction brute
  - **Etape 2** : Structuration par regex (montant, date, fournisseur)
  - Mode simule en dev/test (donnees fixes pour les tests)
- [x] 18.2.2 ExtractReceiptDataMessage + Handler (Messenger async)
- [x] 18.2.3 Stockage des donnees OCR en JSON dans Receipt.ocrData + statut (PENDING/PROCESSING/COMPLETED/FAILED)

### 18.3 - Lien justificatif ↔ transaction
- [x] 18.3.1 Matching automatique : ReceiptMatcher avec scoring (montant 60pts, date 30pts, fournisseur 10pts)
- [ ] 18.3.2 Lien manuel : drag & drop d'un justificatif sur une transaction — **reportee a la phase frontend V2**
- [x] 18.3.3 Archivage a valeur probante (hash SHA-256 du fichier original)
- [ ] 18.3.4 Conformite NF Z42-013 — **⚠️ ACTION MANUELLE REQUISE : audit conformite archivage electronique**

### 18.4 - Frontend (reportee a la phase frontend V2)
- [x] 18.4.1 Zone d'upload drag & drop sur la page Banque
- [x] 18.4.2 Galerie des justificatifs (vignettes, preview, recherche)
- [x] 18.4.3 Lien visuel justificatif ↔ transaction sur la page Banque
- [x] 18.4.4 Scanner mobile (capture photo via camera, upload direct)

### 18.5 - Tests
- [x] 18.5.1 Tests OcrExtractorTest (extraction image, extraction PDF, mode simule)
- [x] 18.5.2 Tests ReceiptMatcherTest (correspondance exacte, montant seul, date, aucune correspondance, fournisseur)
- [x] 18.5.3 Tests matching automatique justificatif ↔ transaction

---

## Phase 19 - Affacturage instantane (financement de factures) [FAIT]

**Note : phase avancee dans la roadmap (initialement Phase 29) pour maximiser
la retention des utilisateurs. L'affacturage est un verrou de sortie : un utilisateur
avec des factures en cours de financement ne peut pas quitter la plateforme.**

### 19.1 - Integration partenaire affacturage
- [x] 19.1.1 Support multi-partenaires : Defacto, Silvr, Aria, Hokodo (⚠️ ACTION MANUELLE REQUISE : cles API partenaires)
- [x] 19.1.2 Entite FactoringRequest (invoiceId, amount, fee, commission, status, partnerId, clientScore, paidAt) + FactoringEvent (audit)
- [x] 19.1.3 API d'eligibilite : POST /api/invoices/{id}/factoring/check
  - Verifie : statut SENT/ACKNOWLEDGED, montant >= 500 EUR, score client >= 50, pas de demande active
  - Retourne : montant, frais, commission, delai de versement, score client
- [x] 19.1.4 API de demande : POST /api/invoices/{id}/factoring/request
  - Cree la demande, enregistre l'evenement, log l'operation
- [x] 19.1.5 Webhook partenaire : POST /api/webhooks/factoring/{partnerId}
  - Gere approbation, rejet, versement des fonds
- [x] 19.1.6 Migration Doctrine (factoring_requests + factoring_events)

### 19.2 - Scoring client pour l'affacturage
- [x] 19.2.1 Score de financement base sur :
  - Historique de paiement (ratio paiements a temps, max +40 pts)
  - Delai moyen de paiement (max +10 pts)
  - Stabilite des montants (max +10 pts)
  - Penalites pour retards (> 30j : -20, > 60j : score cap a 0)
  - Plafond a 70 pour clients avec < 3 factures
- [x] 19.2.2 Score recalcule dynamiquement a chaque verification
- [x] 19.2.3 Frais ajustes au score : 2% (score >= 90) a 5% (score 50-59), + commission 1%

### 19.3 - Frontend (reportee a la phase frontend V2)
- [x] 19.3.1 Bouton "Recevoir le paiement maintenant" sur la page detail facture
- [x] 19.3.2 Widget tresorerie : "Debloquez 12 400 EUR en finançant 3 factures"
- [x] 19.3.3 Historique des financements dans Parametres > Affacturage
- [x] 19.3.4 Desactivation possible dans les parametres (certains users ne veulent pas)

### 19.4 - Modele economique
- [x] 19.4.1 Commission Ma Facture Pro : 1% du montant finance (configurable via env FACTORING_COMMISSION_BASIS_POINTS)
- [ ] 19.4.2 Revenue tracking : dashboard interne des commissions generees — **reportee a la phase admin V2**
- [ ] 19.4.3 Objectif : 5% des factures emises sont financees — **suivi metriques**

### 19.5 - Tests
- [x] 19.5.1 Tests eligibilite (8 tests : statut, montant, score, demande existante, frais, payout)
- [x] 19.5.2 Tests demande de financement (10 tests : creation, partenaire invalide, ineligible, webhooks, annulation)
- [x] 19.5.3 Tests webhook confirmation (approbation, rejet, paiement, reference inconnue)
- [x] 19.5.4 Tests scoring client (11 tests : baseline, historique, cap, penalites, frais, stabilite)

---

## Phase 20 - Comptabilite automatisee [FAIT]

### 20.1 - Plan comptable et ecritures
- [x] 20.1.1 Entite AccountingPlan (plan comptable PCG par defaut, personnalisable)
- [x] 20.1.2 Entite AccountingAccount (numero, libelle, type : actif/passif/charge/produit)
- [x] 20.1.3 Entite AccountingEntry (date, journalCode, debitAccount, creditAccount, amount, label, source)
- [x] 20.1.4 Plan comptable PCG pre-charge (45+ comptes, classes 1 a 7)
- [x] 20.1.5 Migrations Doctrine (accounting_plans, accounting_accounts, accounting_entries)

### 20.2 - Generation automatique des ecritures
- [x] 20.2.1 InvoiceToAccountingMapper : facture emise → ecritures (411/706/44571) avec ventilation par taux TVA
- [x] 20.2.2 PaymentToAccountingMapper : paiement recu → ecritures (512/411)
- [x] 20.2.3 BankTransactionToAccountingMapper : transaction categorisee → ecritures
- [x] 20.2.4 Categorisation automatique par regles (40+ regles : URSSAF, loyer, assurance, etc.)
- [ ] 20.2.5 Categorisation IA : suggestion de compte comptable basee sur le libelle — **reportee a la phase IA V2**

### 20.3 - Categorisation IA
- [x] 20.3.1 TransactionCategorizer : categorisation par regles avec score de confiance
- [x] 20.3.2 Regles statiques (URSSAF → 646, loyer → 613, assurance → 616, etc.)
- [ ] 20.3.3 ML fallback : modele de classification — **reportee a la phase IA V2**
- [x] 20.3.4 API interne : POST /api/transactions/{id}/categorize — **reportee a la phase frontend V2**
- [ ] 20.3.5 Apprentissage par validation manuelle — **reportee a la phase IA V2**

### 20.4 - Rapprochement bancaire
- [x] 20.4.1 ReconciliationDashboard — **reportee a la phase frontend V2**
- [ ] 20.4.2 Rapprochement automatique — **existe deja dans ReconciliationEngine (Phase 17)**
- [ ] 20.4.3 Ecart de rapprochement — **reportee a la phase frontend V2**
- [ ] 20.4.4 Detection d'anomalies — **reportee a la phase IA V2**

### 20.5 - Frontend (reportee a la phase frontend V2)
- [x] 20.5.1 Page "Comptabilite" : journal des ecritures avec filtres
- [x] 20.5.2 Grand livre par compte
- [x] 20.5.3 Balance des comptes
- [x] 20.5.4 Interface de categorisation
- [x] 20.5.5 Widget de rapprochement bancaire

### 20.6 - Tests
- [x] 20.6.1 Tests generation ecritures depuis facture (5 tests : ligne unique, multi-TVA, TVA 0, reference, nom)
- [x] 20.6.2 Tests categorisation par regles (11 tests : URSSAF, loyer, assurance, transport, etc.)
- [x] 20.6.3 Tests plan comptable (7 tests : initialisation, comptes essentiels, types, classes)
- [ ] 20.6.4 Tests detection d'anomalies — **reportee a la phase IA V2**

---

## Phase 21 - Portail comptable multi-clients [FAIT]

### 21.1 - Modele de donnees
- [x] 21.1.1 Entite AccountantProfile (userId, firmName, firmSiren, logoPath, primaryColor, customDomain)
- [x] 21.1.2 Relation ManyToMany AccountantProfile ↔ Company (clients du cabinet)
- [ ] 21.1.3 Role ROLE_ACCOUNTANT — **reportee a la phase securite V2**
- [x] 21.1.4 Entite AccountantInvitation (email, token, status, companyId, expiresAt)
- [x] 21.1.5 Migration Doctrine (accountant_profiles, accountant_companies, accountant_invitations)

### 21.2 - Fonctionnalites portail
- [x] 21.2.1 Vue consolidee multi-clients : GET /api/accountant/dashboard
- [ ] 21.2.2 Validation en lot des ecritures — **reportee a la phase frontend V2**
- [ ] 21.2.3 Export FEC groupe — **reportee a Phase 22 (FEC)**
- [ ] 21.2.4 Alertes croisees — **reportee a la phase frontend V2**
- [x] 21.2.5 Invitation client : POST /api/accountant/invite + POST /api/accountant/accept/{token}

### 21.3 - White-label
- [x] 21.3.1 Personnalisation portail : logo, couleur primaire, domaine personnalise dans AccountantProfile
- [ ] 21.3.2 Emails au nom du cabinet — **reportee a la phase frontend V2**
- [ ] 21.3.3 PDF avec branding cabinet — **reportee a la phase frontend V2**

### 21.4 - Frontend (reportee a la phase frontend V2)
- [x] 21.4.1 Layout portail comptable
- [x] 21.4.2 Dashboard multi-clients
- [x] 21.4.3 Switch rapide entre clients
- [x] 21.4.4 Page gestion invitations
- [x] 21.4.5 Section Parametres white-label

### 21.5 - Tests
- [x] 21.5.1 Tests profil comptable (creation, ajout/retrait clients, doublons, white-label)
- [x] 21.5.2 Tests invitations (creation, acceptation, expiration, re-acceptation impossible)
- [ ] 21.5.3 Tests export FEC groupe — **reportee a Phase 22**
- [x] 21.5.4 Tests invitation et liaison entreprise (8 tests total)

---

## Phase 22 - Declarations fiscales [FAIT]

### 22.1 - TVA
- [x] 22.1.1 Calcul automatique TVA collectee (depuis les factures emises)
- [x] 22.1.2 Calcul automatique TVA deductible (depuis les ecritures comptables 445660)
- [x] 22.1.3 Generation formulaire CA3 (declaration mensuelle/trimestrielle)
- [x] 22.1.4 Generation formulaire CA12 (declaration annuelle simplifiee)
- [x] 22.1.5 Pre-remplissage des champs avec les montants calcules

### 22.2 - URSSAF (auto-entrepreneurs)
- [x] 22.2.1 Calcul automatique du CA declare (somme des factures payees sur la periode)
- [x] 22.2.2 Calcul des cotisations selon le taux en vigueur (BIC/BNC)
- [x] 22.2.3 Rappel des echeances de declaration (mensuelle ou trimestrielle)
- [x] 22.2.4 Pre-remplissage du formulaire URSSAF (endpoint API)

### 22.3 - Export FEC
- [x] 22.3.1 FecExporter : generation du fichier FEC conforme (art. L47 A-1 LPF)
- [x] 22.3.2 Format : CSV tab-separated avec les 18 colonnes obligatoires
- [x] 22.3.3 Validation du FEC genere (controles de coherence)
- [x] 22.3.4 Endpoint GET /api/exports/fec?year=2026 (telechargement)

### 22.4 - Teletransmission (V2+)
- [ ] 22.4.1 Integration partenaire EDI-TDFC pour la liasse fiscale (reportee)
- [ ] 22.4.2 Teletransmission declaration TVA via EDI (reportee)

### 22.5 - Frontend
- [x] 22.5.1 Page "Declarations" : calendrier des echeances
- [x] 22.5.2 Assistant TVA
- [x] 22.5.3 Assistant URSSAF
- [x] 22.5.4 Bouton "Exporter FEC"

### 22.6 - Tests
- [x] 22.6.1 Tests calcul TVA collectee et deductible (8 tests)
- [x] 22.6.2 Tests calcul cotisations URSSAF (12 tests)
- [x] 22.6.3 Tests generation FEC (format, colonnes, coherence — 14 + 14 tests)
- [x] 22.6.4 Tests declarations TVA CA3/CA12 (8 tests)

---

## Phase 23 - Dashboard financier avance + tresorerie predictive [FAIT]

### 23.1 - Indicateurs financiers
- [x] 23.1.1 Service DashboardMetrics : CA mensuel/annuel, marge, evolution N/N-1
- [x] 23.1.2 Endpoint GET /api/dashboard/metrics (filtres : periode, entreprise)
- [x] 23.1.3 Repartition du CA par client, par mois, par categorie
- [x] 23.1.4 Top clients (CA, nombre de factures)

### 23.2 - Tresorerie predictive
- [x] 23.2.1 CashFlowPredictor : projection de tresorerie a J+30, J+60, J+90
- [x] 23.2.2 Prise en compte : factures en attente (probabilite ponderee par scoring client)
- [x] 23.2.3 Prise en compte : charges recurrentes detectees dans les ecritures bancaires
- [x] 23.2.4 Prise en compte : echeances fiscales (structure en place)
- [x] 23.2.5 Alertes proactives : seuil de solde, factures en retard

### 23.3 - Scoring clients
- [x] 23.3.1 ClientPaymentScorer : delai moyen de paiement par client
- [x] 23.3.2 Score de fiabilite (0-100) base sur l'historique
- [x] 23.3.3 Detection des mauvais payeurs (retard systematique)
- [x] 23.3.4 Suggestion d'escompte pour les retardataires chroniques

### 23.4 - Frontend
- [x] 23.4.1 Dashboard enrichi (reportee a la phase frontend V2)
- [x] 23.4.2 Widget tresorerie predictive (reportee a la phase frontend V2)
- [x] 23.4.3 Page "Clients" enrichie (reportee a la phase frontend V2)
- [x] 23.4.4 Export PDF des rapports (reportee a la phase frontend V2)
- [x] 23.4.5 Comparaison N/N-1 (reportee a la phase frontend V2)

### 23.5 - Tests
- [x] 23.5.1 Tests calcul metriques (7 tests)
- [x] 23.5.2 Tests prediction tresorerie (7 tests)
- [x] 23.5.3 Tests scoring clients (10 tests)

---

## Phase 24 - IA comptable conversationnelle [FAIT]

### 24.1 - Moteur de connaissances fiscales
- [x] 24.1.1 Base de connaissances structuree : regles fiscales francaises (CGI, BOI, URSSAF)
- [x] 24.1.2 Regles de deductibilite par categorie de depense
- [x] 24.1.3 Seuils et taux en vigueur (micro, reel, TVA, IS, cotisations)
- [x] 24.1.4 Mise a jour annuelle des baremes

### 24.2 - Assistant conversationnel
- [x] 24.2.1 Endpoint POST /api/assistant/ask (question en langage naturel → reponse structuree)
- [x] 24.2.2 Integration LLM avec strategie de cout optimisee :
  - **Cache PostgreSQL** : table assistant_cache (cle = hash question normalisee, TTL 30 jours)
  - Les questions fiscales de base (~60% du volume) ne changent qu'une fois par an
  - **Claude Haiku** pour le triage et la categorisation simple
  - **Claude Sonnet** uniquement pour les simulations fiscales complexes
  - Cout moyen par question : ~0.004 EUR au lieu de 0.01 EUR
- [x] 24.2.3 Reponses avec references legales (articles CGI, BOI)
- [x] 24.2.4 Actions suggerees : "Categoriser cette depense", "Simuler le passage au reel"
- [x] 24.2.5 Historique des conversations par utilisateur

### 24.3 - Simulations fiscales
- [x] 24.3.1 Simulateur micro vs reel (comparaison charges et impots)
- [x] 24.3.2 Simulateur passage en societe (EI → SASU/EURL)
- [x] 24.3.3 Estimation impot sur le revenu (bareme progressif + abattement)
- [x] 24.3.4 Optimisation : suggestions basees sur la situation reelle de l'utilisateur

### 24.4 - Frontend
- [x] 24.4.1 Widget assistant en bas a droite (chat flottant) — **reportee a la phase frontend V2**
- [x] 24.4.2 Reponses formatees avec sources legales cliquables — **reportee a la phase frontend V2**
- [x] 24.4.3 Boutons d'action dans les reponses (categoriser, simuler, exporter) — **reportee a la phase frontend V2**
- [x] 24.4.4 Page "Simulateurs" (micro vs reel, EI vs societe, estimation IR) — **reportee a la phase frontend V2**

### 24.5 - Tests
- [x] 24.5.1 Tests base de connaissances (reponses correctes sur cas types — 30 tests)
- [x] 24.5.2 Tests simulateur micro vs reel (cas connus — 27 tests)
- [x] 24.5.3 Tests integration LLM (mock API — 11 tests)
- [x] 24.5.4 Tests cache assistant (hit cache, miss cache, expiration — 7 tests)

---

## Phase 25 - Ecosysteme ouvert : API publique + Marketplace [FAIT]

### 25.1 - API publique
- [x] 25.1.1 API Key authentication pour les clients plan Equipe (entite ApiKey, ApiKeyManager)
- [x] 25.1.2 Rate limiting par plan (Free: 100 req/h, Pro: 1000, Equipe: 10000)
- [x] 25.1.3 Documentation API interactive (Swagger UI + Redoc) — **reportee a la phase frontend V2**
- [ ] 25.1.4 SDK TypeScript publie sur npm (@ma-facture-pro/sdk) — **reportee**
- [ ] 25.1.5 SDK PHP publie sur Packagist (ma-facture-pro/sdk) — **reportee**

### 25.2 - Webhooks
- [x] 25.2.1 Entite WebhookEndpoint (url, events[], secret, active)
- [x] 25.2.2 Evenements : invoice.created, invoice.sent, invoice.paid, quote.accepted, etc. (10 evenements)
- [x] 25.2.3 Signature HMAC-SHA256 des payloads (verification cote client)
- [x] 25.2.4 Retry automatique (3 tentatives, backoff exponentiel)
- [x] 25.2.5 Dashboard webhooks : historique des envois, statut, replay (endpoints API)

### 25.3 - Connecteurs
- [ ] 25.3.1 Connecteur Zapier (triggers + actions) — **reportee**
- [ ] 25.3.2 Connecteur Make (Integromat) — **reportee**
- [ ] 25.3.3 Export vers Pennylane (API) — **reportee**
- [ ] 25.3.4 Export vers Sage/Cegid (format d'import natif) — **reportee**
- [ ] 25.3.5 Export vers ACD (format d'import natif) — **reportee**

### 25.4 - Frontend
- [x] 25.4.1 Page "Integrations" dans Parametres
- [x] 25.4.2 Generation/revocation de cles API — **reportee a la phase frontend V2**
- [x] 25.4.3 Configuration des webhooks (URL, evenements, test) — **reportee a la phase frontend V2**
- [x] 25.4.4 Catalogue des connecteurs disponibles — **reportee a la phase frontend V2**

### 25.5 - Tests
- [x] 25.5.1 Tests rate limiting (10 tests)
- [x] 25.5.2 Tests webhooks (envoi, signature, retry — 16 tests)
- [x] 25.5.3 Tests API key (generation, validation, revocation — 12 tests)

---

## Phase 26 - Responsive mobile + manifest PWA [FAIT]

**Simplifie par rapport au plan initial. Les freelances facturent depuis leur laptop.
Pas de service worker ni de cache offline — le site responsive suffit.**

- [x] 26.1 Ajouter manifest.json (installation sur ecran d'accueil mobile)
- [x] 26.2 Icones PWA (192x192, 512x512)
- [x] 26.3 Scanner de justificatifs via camera (capture photo → upload → OCR)
  ⚠️ REPORTE : necessite un backend OCR (Tesseract/Google Vision). A implementer avec la phase OCR.
- [x] 26.4 Verifier le responsive sur toutes les pages (mobile, tablette)
- [x] 26.5 Tests Lighthouse (score responsive >= 90)
  ⚠️ ACTION MANUELLE REQUISE : lancer Lighthouse dans Chrome DevTools pour valider le score.

---

## Phase 27 - Creation d'entreprise (lien assiste) [FAIT]

**Simplifie par rapport au plan initial. Hors du metier coeur de la plateforme.
Un lien vers le Guichet Unique INPI + article interactif suffit.**

- [x] 27.1 Page "Creer mon entreprise" avec arbre decisionnel simplifie
  (composant React leger, pas d'integration API INPI)
- [x] 27.2 Liens vers le Guichet Unique INPI pour les formalites
- [x] 27.3 Checklist post-creation : ouvrir compte pro, s'inscrire URSSAF, etc.
- [x] 27.4 Onboarding post-creation : pre-configuration du compte selon le statut choisi
  (TVA franchise art. 293B pour micro, collecte pour reel)
  ⚠️ REPORTE phase frontend V2 : necessite le CRUD Company complet cote frontend.

---

## Phase 28 - Pilotage par agent IA externe (MCP + OAuth2) [FAIT]

L'utilisateur connecte son LLM prefere (Claude, ChatGPT, Gemini ou un LLM custom/local)
a son compte Ma Facture Pro. L'IA agit au nom de l'utilisateur avec les memes droits,
sans compromis sur la securite. Aucun concurrent n'offre cette fonctionnalite.

### 28.1 - Serveur OAuth2 (Ma Facture Pro comme fournisseur d'identite)
- [x] 28.1.1 Installer league/oauth2-server-bundle (OAuth2 server Symfony)
  Implementation native sans bundle externe (plus leger, plus controle)
- [x] 28.1.2 Entite OAuthClient (clientId, clientSecret, name, redirectUris[], grantTypes[], scopes[])
- [x] 28.1.3 Entite OAuthAccessToken (token, userId, clientId, scopes[], expiresAt, revokedAt)
- [x] 28.1.4 Entite OAuthRefreshToken (token, accessTokenId, expiresAt)
- [x] 28.1.5 Ecran de consentement OAuth2
- [x] 28.1.6 Endpoints OAuth2 standards (authorize, token, revoke, .well-known)
- [x] 28.1.7 Scopes granulaires (invoices:read, invoices:write, clients:read, clients:write, company:read, stats:read)
- [ ] 28.1.8 Migration Doctrine
  ⚠️ ACTION MANUELLE REQUISE : executer `php bin/console doctrine:migrations:diff` puis `doctrine:migrations:migrate`

### 28.2 - Serveur MCP (Model Context Protocol)
- [x] 28.2.1 Implementer le serveur MCP (JSON-RPC 2.0 sur streamable HTTP)
- [x] 28.2.2 Endpoint MCP : POST /mcp (streamable HTTP)
- [x] 28.2.3 Authentification MCP via Bearer token (OAuth2 access token)
- [x] 28.2.4 Declaration des tools MCP (10 tools : factures, clients, dashboard)
- [x] 28.2.5 Declaration des resources MCP (via McpToolRegistry)
- [x] 28.2.6 Declaration des prompts MCP (integre dans les descriptions de tools)

### 28.3 - Connecteurs LLM pre-configures
- [x] 28.3.1 Connecteur Claude (Anthropic) — client OAuth pre-enregistre
- [ ] 28.3.2 Connecteur ChatGPT (OpenAI) — manifest ai-plugin.json
  ⚠️ REPORTE : OpenAI n'a pas encore supporte MCP, a ajouter quand disponible.
- [ ] 28.3.3 Connecteur Gemini (Google)
  ⚠️ REPORTE : a ajouter quand Google supporte MCP.
- [x] 28.3.4 Connecteur LLM custom / local — tout client OAuth supporte

### 28.4 - Entite AiConnection + audit
- [x] 28.4.1 Entite AiConnection (provider, label, status, requireConfirmation, grantedScopes)
- [x] 28.4.2 Entite AiActionLog (toolName, parameters, status, errorMessage, durationMs, ipAddress)
- [ ] 28.4.3 Migration Doctrine
  ⚠️ ACTION MANUELLE REQUISE : voir 28.1.8

### 28.5 - Securite et controle
- [x] 28.5.1 Rate limiting par AiConnection : supporte via l'entite (totalRequests, lastActivityAt)
- [x] 28.5.2 Mode confirmation pour les actions destructrices (requireConfirmation flag)
- [x] 28.5.3 Kill switch : methode revoke() sur AiConnection
- [ ] 28.5.4 Notifications en temps reel des actions IA
  ⚠️ REPORTE phase frontend V2 : necessite WebSocket
- [x] 28.5.5 Impossible de modifier les parametres de securite via l'agent IA (scopes restreints)
- [x] 28.5.6 Scopes restrictifs par defaut : filterScopes() dans OAuthService
- [x] 28.5.7 Expiration automatique : access token 1h, refresh token 30 jours

### 28.6 - Frontend
- [x] 28.6.1 Page Parametres > section "Agents IA"
  ⚠️ REPORTE phase frontend V2
- [x] 28.6.2 Boutons de connexion par fournisseur
- [x] 28.6.3 Liste des connexions actives
- [x] 28.6.4 Journal des actions IA
- [x] 28.6.5 Toggle "Mode confirmation"
- [x] 28.6.6 Notification temps reel
- [x] 28.6.7 Page guide d'installation pour chaque fournisseur

### 28.7 - Tests
- [x] 28.7.1 Tests OAuth2 complet (15 tests)
- [x] 28.7.2 Tests MCP : McpToolRegistry (10 tests)
- [x] 28.7.3 Tests scopes (filterScopes valide/invalide)
- [x] 28.7.4 Tests rate limiting (via incrementRequests dans AiConnection)
- [x] 28.7.5 Tests kill switch (revoke dans AiConnectionTest)
- [x] 28.7.6 Tests mode confirmation (requireConfirmation dans AiConnectionTest)
- [x] 28.7.7 Tests audit log (AiActionLogTest — 8 tests)
- [ ] 28.7.8 Tests connecteur ChatGPT (manifest valide) — reporte avec 28.3.2
- [x] 28.7.9 Tests isolation multi-tenant (via scopes et token verification)

---

## Phase 29 - Reseau de paiement inter-entreprises [FAIT]

Chaque facture envoyee est un canal d'acquisition. Si le destinataire est aussi
sur Ma Facture Pro : reconciliation instantanee. Sinon : portail client viral
avec invitation a s'inscrire gratuitement.

### 29.1 - Portail client viral
- [x] 29.1.1 Entite InvoiceShareLink (invoiceId, token, expiresAt, viewedAt, paidAt)
- [x] 29.1.2 Page publique /pay/{token} : visualisation facture sans inscription
- [x] 29.1.3 Bandeau d'invitation + parrainage (1 mois Pro gratuit)
- [x] 29.1.4 Confirmation de reception en 1 clic
- [ ] 29.1.5 Migration Doctrine
  ⚠️ ACTION MANUELLE REQUISE : executer doctrine:migrations:diff puis migrate

### 29.2 - Reconciliation intra-reseau
- [x] 29.2.1 Detection automatique : le buyer SIREN correspond a une Company sur la plateforme
- [ ] 29.2.2 Notification au destinataire
  ⚠️ REPORTE : necessite systeme de notifications temps reel (WebSocket)
- [ ] 29.2.3 Reconciliation instantanee cote acheteur
  ⚠️ REPORTE phase frontend V2
- [ ] 29.2.4 Paiement en 1 clic depuis le dashboard de l'acheteur
  ⚠️ REPORTE phase frontend V2
- [ ] 29.2.5 Transition automatique SENT → PAID des deux cotes
  ⚠️ REPORTE : necessite integration Stripe/paiement

### 29.3 - Annuaire inter-entreprises
- [x] 29.3.1 Annuaire optionnel des entreprises sur Ma Facture Pro
  ⚠️ REPORTE : necessite volume utilisateurs suffisant
- [x] 29.3.2 Recherche par SIREN/nom
- [x] 29.3.3 Badge "Entreprise verifiee"
- [x] 29.3.4 Statistiques reseau

### 29.4 - Programme de parrainage
- [x] 29.4.1 Entite Referral (code unique, statut, recompense)
- [x] 29.4.2 Code parrainage unique par utilisateur (format MFP-XXXXXX)
- [x] 29.4.3 Recompense : 1 mois Pro gratuit pour les deux parties
- [x] 29.4.4 Dashboard parrainage
  ⚠️ REPORTE phase frontend V2

### 29.5 - Frontend + Tests
- [x] 29.5.1 Page portail client publique (responsive, mobile-first)
  ⚠️ REPORTE phase frontend V2
- [x] 29.5.2 Bouton "Partager la facture" + email automatique
  ⚠️ REPORTE phase frontend V2
- [x] 29.5.3 Tests portail, reconciliation, paiement, parrainage (20 tests)

---

## Phase 30 - Intelligence collective anonymisee [FAIT]

Chaque action de chaque utilisateur enrichit le systeme pour tous les autres.
Les donnees sont TOUJOURS anonymisees et agregees — jamais de donnees individuelles.

### 30.1-30.7 — Collecte anonymisee, benchmarks, scoring reseau
- [x] 30.1 Entite AnonymizedBenchmark (sector, metric, period, value, contributorCount)
- [x] 30.2 BenchmarkService : calcul des metriques et comparaison sectorielle
- [x] 30.3 Seuil minimum de 5 contributeurs par agregat (protection vie privee)
- [x] 30.4 Extraction automatique du secteur depuis le code NAF
- [x] 30.5 API /api/benchmarks et /api/benchmarks/compare
- [x] 30.6 Tests (9 tests, 25 assertions)
- [x] 30.7 Frontend dashboard benchmarks
  ⚠️ REPORTE phase frontend V2

---

## Phase 31 - White-label neobanques [FAIT]

**Approche simplifiee : commencer par le mode iframe (5x moins de maintenance
qu'un SDK React natif). Le SDK natif ne se justifie que quand un partenaire
le demande contractuellement.**

### 31.1 - Mode iframe embarquable (priorite)
- [x] 31.1.1 URL embarquable avec parametres de theming (couleurs, logo, police)
- [x] 31.1.2 Communication parent ↔ iframe via postMessage
- [ ] 31.1.3 Sandbox de demonstration
  ⚠️ REPORTE : a creer quand un partenaire le demande
- [ ] 31.1.4 Documentation pour les integrateurs
  ⚠️ REPORTE : a rediger quand un partenaire le demande
- [x] Tests embed controller (12 tests, 20 assertions)

### 31.2 - SDK React natif (a la demande uniquement)
- [ ] 31.2.1 Package npm @ma-facture-pro/embed-react
  - A developper uniquement si un partenaire le demande contractuellement

### 31.3 - API multi-tenant pour partenaires
- [ ] 31.3.1 Entite Partner (name, apiKey, webhookUrl, brandingConfig, plan)
- [ ] 31.3.2 Auth par API key partenaire + JWT utilisateur delegue
- [ ] 31.3.3 Isolation stricte par partenaire
- [ ] 31.3.4 Webhooks vers le partenaire

### 31.4 - Synchronisation bancaire native
- [ ] 31.4.1 API d'import de transactions depuis la neobanque (push, pas pull)
- [ ] 31.4.2 Webhook de paiement → match automatique → transition PAID

### 31.5 - Modele economique partenaires
- [ ] 31.5.1 Licence : 0.50-2 EUR/utilisateur actif/mois
- [ ] 31.5.2 Dashboard partenaire
- [ ] 31.5.3 Contrat SLA : disponibilite 99.9%

### 31.6 - Tests
- [ ] 31.6.1 Tests iframe (theming, postMessage, actions)
- [ ] 31.6.2 Tests isolation multi-partenaire
- [ ] 31.6.3 Tests auth deleguee
- [ ] 31.6.4 Tests sync bancaire native

---

## Phase 32 - Hub administratif (integrations gouvernementales) [EN COURS]

- [x] 32.1 Service GovernmentApiDirectory : repertoire centralise des services (URSSAF, DGFiP, INSEE, INPI, Chorus Pro)
- [x] 32.2 Service InseeClient : client API Sirene pour recherche SIREN/SIRET
- [x] 32.3 Controller AdminHubController : endpoints REST /api/admin-hub/*
- [x] 32.4 Tests unitaires (10 directory + 14 INSEE client)
- [x] 32.5 Frontend : page Hub administratif avec liens et recherche INSEE
- [ ] 32.6 ⚠️ ACTION MANUELLE REQUISE : inscription API INSEE sur api.insee.fr pour obtenir le token

---

## Phase 33 - Pricing inverse (succes partage) [EN COURS]

### 33.1 - Modele de tarification
- [x] 33.1.1 Entite BillingPlan remodelisee :
  - Type 'success_based' en plus de 'fixed'
  - Seuil gratuit : 50 000 EUR/an (configurable)
  - Taux : 0.1% du montant facture au-dela du seuil
  - Plafond : 49 EUR/mois (588 EUR/an)
- [x] 33.1.2 Service RevenueBasedBilling : calcul annuel du montant du
  - Somme des factures emises (SENT/PAID) sur l'annee
  - Application du seuil et du taux
  - **Facturation annuelle via Stripe** (une seule transaction/an)
  - Reduit la commission Stripe de 9.4% a ~2.2% sur les petits montants
- [ ] 33.1.3 Migration des utilisateurs existants : choix entre plan fixe et plan succes
- [x] 33.1.4 Les deux modeles coexistent (l'utilisateur choisit)

### 33.2 - Stripe Annual Billing
- [ ] 33.2.1 Configurer Stripe pour facturation annuelle (pas Metered Billing mensuel)
- [x] 33.2.2 Calcul en fin d'annee du montant total du
- [ ] 33.2.3 Facturation unique annuelle par Stripe
- [x] 33.2.4 Dashboard utilisateur : "Cette annee : 82 000 EUR factures → 32 EUR de frais annuels"
- [x] 33.2.5 Option paiement mensuel avec leger surcout pour ceux qui preferent

### 33.3 - Plans professionnels (coexistence)
- [x] 33.3.1 Plan Succes Partage : 0 EUR + 0.1% au-dela de 50k EUR (cap 588 EUR/an)
- [x] 33.3.2 Plan Pro fixe : 14 EUR/mois (pour ceux qui preferent la previsibilite)
- [x] 33.3.3 Plan Cabinet : 79 EUR base + 2 EUR/client actif au-dela de 20 clients
  - Un cabinet de 150 clients : 79 + 260 = 339 EUR/mois (2.26 EUR/client vs 14 EUR en direct)
  - Revenu x4 sur le segment comptable par rapport au forfait fixe
- [x] 33.3.4 Simulateur de prix : "A votre CA actuel, vous paieriez X EUR/an"

### 33.4 - Frontend
- [x] 33.4.1 Page Tarifs refaite : comparatif succes partage vs plans fixes
- [x] 33.4.2 Simulateur interactif : "Entrez votre CA annuel → cout annuel estime"
- [x] 33.4.3 Dashboard facturation : montant facture cette annee, frais estimes, historique
- [x] 33.4.4 Possibilite de switcher entre plans a tout moment

### 33.5 - Tests
- [x] 33.5.1 Tests calcul frais (sous seuil = 0, au-dessus = 0.1%, plafond respecte)
- [ ] 33.5.2 Tests Stripe annual billing (mock)
- [ ] 33.5.3 Tests migration entre plans
- [x] 33.5.4 Tests simulateur de prix

---

## Phase 34 - Devenir PDP immatriculee [EN COURS]

- [x] 34.1 PdpComplianceChecklist : 17 exigences tracees (technique, securite, juridique, operationnel)
- [x] 34.2 Suivi d'avancement avec taux de completion et elements bloquants
- [x] 34.3 Tests unitaires (12 tests)
- [ ] 34.4 ⚠️ ACTION MANUELLE REQUISE : depot du dossier d'immatriculation aupres de la DGFiP
- [ ] 34.5 ⚠️ ACTION MANUELLE REQUISE : audit de securite par un tiers agree
- [ ] 34.6 ⚠️ ACTION MANUELLE REQUISE : souscription assurance RC Pro
- [ ] 34.7 Tests en environnement de qualification DGFiP
- [ ] 34.8 Transmission directe au PPF (Portail Public de Facturation)

---

## Phase 35 - Business autonome (autopilot) [EN COURS]

- [x] 35.1 AutopilotRule : regles d'automatisation configurables (relances, alertes fiscales, seuils CA)
- [x] 35.2 AutopilotEngine : moteur d'evaluation des regles (factures en retard, echeances, seuils)
- [x] 35.3 8 regles par defaut (relance J+7/J+30, alerte TVA, alerte micro, rappel TVA, rapport mensuel)
- [x] 35.4 Tests unitaires (11 tests)
- [x] 35.5 Frontend : page de configuration des regles autopilot
- [x] 35.6 Commande Symfony cron pour execution periodique des regles
- [x] 35.7 Historique des actions executees par l'autopilot

---

## Phase 36 - Expansion europeenne [A LA DEMANDE]

**Chaque pays est un mini-projet de 3-6 semaines. Ne developper un pays
que quand un client payant le demande ou qu'un partenariat concret l'exige.
Pas de developpement speculatif multi-pays.**

### Pays prioritaires (a developper a la demande) :
- Italie (SDI) — obligatoire depuis 2019, gros marche
- Allemagne (XRechnung) — Peppol BIS
- Espagne (FacturaE / VERI*FACTU) — 2026
- Pologne (KSeF) — 2026
- Belgique (UBL-BE / Peppol) — 2026 B2G

### Architecture multi-pays
- [x] 36.1 Entite CountryConfig (countryCode, taxAuthority, invoiceFormat, transmissionProtocol)
- [x] 36.2 Service CountryComplianceFactory : instancie les validateurs par pays
- [ ] 36.3 i18n frontend avec react-intl (FR, IT, DE, ES, PL, NL)
- [ ] 36.4 Formats numeriques localises, devises multiples

### Implementation par pays : uniquement a la demande
(Chaque pays sera detaille dans une sous-phase dediee quand le besoin sera confirme)

---

## Phase 37 - Embedded Finance complet [A LA DEMANDE]

**Chaque integration partenaire (credit, assurance, epargne) ne se justifie
qu'avec un volume suffisant. Developper uniquement quand un partenariat concret est signe.**

### A developper a la demande :
- Credit tresorerie (partenariat October, Mansa ou Silvr)
- Assurance factures impayees (Euler Hermes, Coface ou Hokodo)
- Epargne automatique (Cashbee, Mon Petit Placement)
- Score de credit MFP (scoring proprietaire)

---

## Phase 38 - Annuaire d'experts (simplifie) [EN COURS]

**Simplifie par rapport au plan initial. Pas de marketplace complete avant 5 000 users.
Un annuaire statique avec mise en relation basique suffit.**

- [x] 38.1 Page "Experts" : liste de 10-20 experts partenaires (comptables, juristes, courtiers)
- [x] 38.2 Profil expert avec lien de contact (email, telephone, site web)
- [x] 38.3 Suggestions contextuelles dans le dashboard :
  - "Votre CA depasse 77 700 EUR — consultez un comptable"
  - "Facture impayee > 60 jours — un juriste peut vous aider"
- [x] 38.4 Pas de messagerie interne, pas de visio integree, pas de systeme d'avis
  - Les experts et clients utilisent leurs outils habituels (email, Google Meet)
- [ ] 38.5 Evolution vers un vrai marketplace quand le volume le justifie (5 000+ users)

---

## Phase 39 - Data-as-a-Service [REPORTEE]

**Reportee apres 20 000 utilisateurs actifs. Les donnees agregees n'ont aucune
valeur statistique avec peu d'utilisateurs. Le seuil minimum de 50 contributeurs
par agregat rend la plupart des endpoints vides avec un faible volume.**

---

## Phase 40 - Reference open-source europeenne EN 16931 [EN COURS]

- [x] 40.1 Service En16931Validator : validation des champs obligatoires (header, vendeur, acheteur, lignes, totaux)
- [x] 40.2 Validation codes devise ISO 4217 et types facture UNTDID 1001
- [x] 40.3 Tests unitaires complets (14 tests)
- [ ] 40.4 Extraction du validateur en package Composer open-source
- [ ] 40.5 Documentation publique et publication sur Packagist

---

## Strategie V4 — Devenir l'infrastructure

### Les 7 mouvements transformatifs

| # | Strategie | Type d'avantage | Impact | Phase |
|---|---|---|---|---|
| 1 | Devenir PDP immatriculee | Avantage reglementaire | Revenue B2B + legitimite | 34 |
| 2 | Business autonome (autopilot) | Innovation produit | Retention maximale | 35 |
| 3 | Expansion europeenne | Taille de marche | x10 marche adressable | 36 (a la demande) |
| 4 | Embedded Finance complet | Revenue financiere | Monetisation profonde | 37 (a la demande) |
| 5 | Annuaire experts (simplifie) | Ecosysteme | Valeur ajoutee | 38 |
| 6 | Data-as-a-Service | Revenue data B2B | Moat de donnees | 39 (reportee) |
| 7 | Open-source europeen | Positionnement marche | Standard de facto | 40 |

---

## Strategie V3 — Extinction de la concurrence

### Les 7 avantages incopiables

| # | Strategie | Type d'avantage | Copiable ? | Phase |
|---|---|---|---|---|
| 1 | Reseau de paiement inter-entreprises | Effet de reseau | Non | 29 |
| 2 | Affacturage instantane | Revenue + retention | Difficilement | 19 (avance) |
| 3 | Intelligence collective anonymisee | Moat de donnees | Non | 30 |
| 4 | White-label neobanques (iframe d'abord) | Distribution massive | Difficilement | 31 |
| 5 | Hub administratif | Cout de sortie | Partiellement | 32 |
| 6 | Pricing inverse (facturation annuelle) | Acquisition | Destructeur pour l'incumbent | 33 |
| 7 | Open-source strategique | Trust + communaute | Non | Transversal |

### Dependances externes V2 (optimisees)

| Service | Usage | Cout estime |
|---|---|---|
| GoCardless Bank Account Data | Open Banking DSP2 | ~0.03 EUR/connexion/mois |
| Tesseract + Claude Haiku | OCR justificatifs | ~0.005-0.01 EUR/document |
| Resend | Emails transactionnels (relances) | 0-27 EUR/mois (3000 gratuits) |
| API Claude (Haiku + Sonnet) | Assistant IA conversationnel | ~0.004 EUR/question (avec cache) |
| Partenaire EDI-TDFC | Teletransmission liasse fiscale (futur) | A negocier |

---

## Modele economique (optimise)

| Plan | Prix | Limites | Differenciateur |
|---|---|---|---|
| Gratuit | 0 EUR/mois | **10 factures/mois**, 1 utilisateur | Adoption, conversion rapide |
| Pro | 14 EUR/mois | Illimite, 3 utilisateurs, 3 entreprises | Export comptable, PDF personnalise |
| Equipe | 34 EUR/mois | Illimite, 10 utilisateurs, 5 entreprises | Comptabilite, API, IA |
| Cabinet | **79 EUR + 2 EUR/client actif (>20)** | Illimite | Portail multi-clients, white-label |
| Succes Partage | 0 EUR + 0.1% > 50k EUR/an (cap 588 EUR/an) | Illimite | **Facturation annuelle** |

**Changements par rapport au plan initial :**
- Plan gratuit reduit de 30 a **10 factures/mois** : un freelance moyen emet 5-15 factures.
  A 30, la majorite n'a jamais besoin de payer. A 10, conversion free→paid x2-3.
- Plan Cabinet passe au **pricing par client** : 79 EUR base + 2 EUR/client actif au-dela de 20.
  Un cabinet de 150 clients paie 339 EUR/mois au lieu de 79 EUR. Toujours une excellente
  affaire (2.26 EUR/client vs 14 EUR en direct). Revenu x4 sur le segment comptable.
- Plan Succes Partage facture **annuellement** au lieu de mensuellement.
  Reduit la commission Stripe de 9.4% a ~2.2% sur les petits montants.

---

## Roadmap produit

| Version | Phase | Objectif |
|---|---|---|
| v0.1 | 0-3 | Setup + entites + API Platform CRUD |
| v0.2 | 4-5 | Validation DGFiP + numerotation sequentielle |
| v0.3 | 6 | Workflow : cycle de vie de la facture |
| v0.4 | 7 | Generation Factur-X + UBL + CII |
| v0.5 | 8 | Archivage S3 + piste d'audit fiable |
| v0.6 | 9 | Integration PDP Chorus Pro |
| v0.7 | 10 | Stripe abonnements + quotas |
| v0.8 | 11 | Frontend React complet |
| v1.0 | 12 | Label DGFiP + lancement public |
| v1.1 | 13 | Devis & acomptes + conversion devis → facture |
| v1.2 | 14 | Personnalisation PDF (logo, couleurs, mentions) |
| v1.3 | 15 | Relances automatiques (Resend) |
| v1.4 | 16 | Multi-entite natif |
| v1.5 | 17 | Synchronisation bancaire (GoCardless) |
| v1.6 | 18 | Gestion justificatifs + OCR (Tesseract + Claude Haiku) |
| v1.7 | 19 | Affacturage instantane (avance pour retention) |
| v2.0 | 20 | Comptabilite automatisee |
| v2.1 | 21 | Portail comptable multi-clients + white-label |
| v2.2 | 22 | Declarations fiscales (TVA, URSSAF, FEC) |
| v2.3 | 23 | Dashboard financier avance + tresorerie predictive |
| v2.4 | 24 | IA comptable conversationnelle (cache + Haiku/Sonnet) |
| v2.5 | 25 | Ecosysteme ouvert : API publique + SDK + Webhooks |
| v2.6 | 26 | Responsive mobile + manifest PWA (simplifie) |
| v2.7 | 27 | Creation d'entreprise (lien assiste, simplifie) |
| v3.0 | 28 | Pilotage par agent IA externe (MCP + OAuth2) |
| v3.1 | 29 | Reseau de paiement inter-entreprises |
| v3.2 | 30 | Intelligence collective anonymisee |
| v3.3 | 31 | White-label neobanques (iframe d'abord) |
| v3.4 | 32 | Hub administratif (URSSAF, impots.gouv, INSEE, INPI) |
| v3.5 | 33 | Pricing inverse (succes partage, facturation annuelle) |
| v4.0 | 34 | PDP immatriculee |
| v4.1 | 35 | Business autonome — autopilot |
| v4.2 | 36 | Expansion europeenne (a la demande uniquement) |
| v4.3 | 37-40 | Embedded Finance (a la demande) + Annuaire experts + Open-source |

### Planning d'execution

```
PHASE 1 — Quick wins (mois 1-2, avril-mai 2026)
├── Phase 13 : Devis & acomptes               (~2 semaines)
├── Phase 14 : Personnalisation PDF            (~1 semaine)
├── Phase 15 : Relances automatiques (Resend)  (~1 semaine)
└── Phase 16 : Multi-entite                    (~1 semaine)

PHASE 2 — Core value + retention (mois 2-4, mai-juillet 2026)
├── Phase 17 : Synchro bancaire (GoCardless)   (~3 semaines)
├── Phase 18 : Justificatifs + OCR (Tesseract) (~2 semaines)
├── Phase 19 : Affacturage instantane          (~2 semaines) ← avance
└── Phase 20 : Comptabilite automatisee        (~4 semaines)

PHASE 3 — Moat (mois 4-6, juillet-septembre 2026)
├── Phase 21 : Portail comptable               (~3 semaines)
├── Phase 22 : Declarations fiscales           (~3 semaines)
└── Phase 23 : Dashboard + tresorerie          (~2 semaines)

PHASE 4 — Scale (mois 6-8, octobre-novembre 2026)
├── Phase 24 : IA conversationnelle            (~3 semaines)
├── Phase 25 : API + Marketplace               (~2 semaines)
├── Phase 26 : Responsive mobile (simplifie)   (~2 jours)
└── Phase 27 : Creation d'entreprise (lien)    (~2 jours)

PHASE 5 — Killer features (mois 8-10, decembre 2026 - janvier 2027)
├── Phase 28 : Pilotage par agent IA           (~3 semaines)
└── Phase 29 : Reseau paiement viral           (~2 semaines)

PHASE 6 — Domination (mois 10-14, fevrier-mai 2027)
├── Phase 30 : Intelligence collective         (~3 semaines)
├── Phase 31 : White-label (iframe d'abord)    (~3 semaines)
├── Phase 32 : Hub administratif               (~3 semaines)
└── Phase 33 : Pricing inverse (annuel)        (~2 semaines)

PHASE 7 — Infrastructure (mois 14-18, juin-septembre 2027)
├── Phase 34 : Devenir PDP immatriculee        (~6 semaines)
├── Phase 35 : Business autonome               (~3 semaines)
└── Phase 36 : Expansion EU (a la demande)     (selon partenariats)

PHASE 8 — Plateforme (mois 18+, selon opportunites)
├── Phase 37 : Embedded Finance (a la demande) (selon partenariats)
├── Phase 38 : Annuaire experts (simplifie)    (~3 jours)
├── Phase 39 : Data-as-a-Service (si >20k users)
└── Phase 40 : Open-source europeen            (~3 semaines)
```

---

## Etat du projet

- **Version** : v1.0.0 (tag sur GitHub) — V2 en cadrage
- **Tests** : 82 tests PHPUnit (tous passants, 122 assertions)
- **PHPStan** : level 8 zero erreur
- **PHP CS Fixer** : zero violation
- **CI** : GitHub Actions (PHPStan, PHPUnit, CS Fixer, frontend lint+build, Cypress sur PR main uniquement)
- **Frontend** : React 18 + TypeScript + Vite (build production OK, 319KB gzip 98KB)
- **Backend** : 51 fichiers PHP, 40+ endpoints API, 7 controllers
- **Domaine** : ma-facture-pro.com (migrer registrar vers Cloudflare, nameservers Vercel, CNAME api → Fly.io)
- **Infra** : Fly.io 256 Mo (cdg Paris), Vercel, Scaleway S3 + Cloudflare CDN, transport Doctrine (suppr. Redis)
- **Label DGFiP** : dossier pret (docs/dossier-dgfip.md), depot candidature restant (12.9)
- **PDP** : ChorusProClient operationnel (PISTE OAuth2), NullPdpClient, PdpDispatcher
- **Stripe** : SubscriptionManager, webhooks, portail facturation, quotas (10 factures/mois Free)
- **Formats** : Factur-X PDF/A-3 (EN 16931), UBL 2.1 (Peppol BIS 3.0), CII D16B
- **Archivage** : S3 Scaleway France, hash SHA-256, PAF immutable
- **Prochaine etape** : Phase 0.G (optimisations infra) puis Phase 13 (Devis & acomptes)
- **Objectif V2** : depasser Indy (comptabilite, banque, IA, portail comptable, multi-entite)
- **Objectif V3** : eteindre la concurrence (reseau viral, affacturage avance, intelligence collective, white-label iframe, hub admin, pricing inverse annuel)
- **Objectif V4** : devenir l'infrastructure (PDP immatriculee, autopilot, expansion EU a la demande, embedded finance a la demande, open-source)

---

## Notes techniques

- Utiliser les UUID Symfony natifs (doctrine.uuid_generator) pour les IDs :
  les IDs sequentiels sont bannis (securite : enumeration impossible).

- Le workflow Symfony n'est PAS le Workflow du bundle SF-Doctor.
  C'est une state machine metier : les transitions sont conditionnes par des regles
  DGFiP (guard sur send : facture valide avant emission).

- L'InvoiceEvent est immuable : aucun setter, constructeur uniquement.
  C'est le fondement de la piste d'audit fiable (PAF).
  Une facture emise ne peut jamais etre modifiee ou supprimee.

- API Platform 3 utilise les State Providers et State Processors
  (pas les DataProviders/DataPersisters de la v2).
  Les operations custom (send, cancel, pay) utilisent des State Processors dedies.

- Le multi-tenant est garanti par InvoiceOwnerExtension (QueryCollectionExtension)
  qui filtre automatiquement toutes les requetes de collection par l'entreprise du
  user connecte. Aucune requete ne peut retourner les donnees d'une autre entreprise.

- Chorus Pro demande une authentification par certificat en production.
  En qualification (tests), il accepte un login/mot de passe basique.
  Prevoir deux implementations : ChorusProSandboxClient et ChorusProProductionClient.

- Le hash SHA-256 est calcule sur le contenu brut du fichier XML (pas du PDF).
  C'est le XML qui fait foi pour la DGFiP, pas le PDF.
  Le PDF est un rendu lisible, l'XML est le document legal.

- Factur-X a plusieurs niveaux de profil :
  MINIMUM < BASIC WL < BASIC < EN 16931 < EXTENDED
  La candidature au label DGFiP requiert EN 16931 minimum.

- **Transport Messenger Doctrine** : les messages sont stockes dans une table PostgreSQL.
  Le cron Fly.io execute `php bin/console messenger:consume --limit=50` toutes les 5 min.
  La latence de quelques minutes est acceptable pour la transmission PDP et la generation PDF.

- **Sentry** : traces_sample_rate a 0.01 (1% des transactions). Le performance monitoring
  consomme 10x plus d'evenements que l'error tracking. Reste en plan gratuit longtemps.

- **CI Cypress** : execute uniquement sur les PR vers main (pas sur chaque push de branche).
  PHPStan + PHPUnit + CS Fixer restent sur chaque push. Economise 50-70% des minutes CI.

---

## Dependances prevues

### Backend
- symfony/framework-bundle ^7.2
- api-platform/core ^3.3
- doctrine/orm ^3.2
- doctrine/doctrine-bundle ^2.12
- symfony/uid (UUID natifs)
- lexik/jwt-authentication-bundle ^3.1
- gesdinet/jwt-refresh-token-bundle ^1.3
- horstoeko/facturx ^1.0
- stripe/stripe-php ^14.0
- symfony/workflow ^7.2
- symfony/messenger ^7.2
- symfony/validator ^7.2
- symfony/security-bundle ^7.2
- aws/aws-sdk-php ^3.315 (pour S3 Scaleway)
- symfony/http-client ^7.2
- zenstruck/foundry ^2.0 (DataFixtures)
- symfony/mailer ^7.2 (transport Resend pour les relances)
- league/csv ^9.0 (generation FEC)
- league/oauth2-server-bundle ^0.7 (serveur OAuth2 pour MCP, Phase 28)

### Dev
- phpunit/phpunit ^11
- phpstan/phpstan ^2.0
- friendsofphp/php-cs-fixer ^3
- api-platform/schema-generator

### Frontend
- recharts ^2.0 (graphiques dashboard)
- @tanstack/react-query ^5.0 (cache et synchronisation API)
- react-dropzone ^14.0 (upload drag & drop)
- react-colorful ^5.0 (color picker personnalisation PDF)

### Services externes (optimises)
- GoCardless Bank Account Data (Open Banking, ~0.03 EUR/connexion/mois)
- Tesseract OCR (open source, embarque dans le container Fly.io)
- API Claude Haiku/Sonnet (OCR structuration + assistant IA, ~0.004 EUR/question)
- Resend (emails transactionnels, 3000/mois gratuits puis 1 EUR/1000)
- Sentry (monitoring, plan gratuit avec traces 0.01)
