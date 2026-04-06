# Dossier de candidature — Label "Solution compatible – Facturation electronique"

**Editeur :** Pierre-Arthur Demengel
**Solution :** Ma Facture Pro
**Date :** avril 2026
**Version :** 1.0.0

---

## 1. Description fonctionnelle

### 1.1 Presentation de la solution

Ma Facture Pro est un logiciel SaaS de facturation electronique destine aux freelances,
auto-entrepreneurs et PME francaises. Il permet de creer, emettre, recevoir et
archiver des factures conformes a la reforme de la facturation electronique
(Decret n. 2022-1299 du 7 octobre 2022).

### 1.2 Fonctionnalites principales

| Fonctionnalite | Description |
|---|---|
| Creation de factures | Saisie guidee avec calcul automatique des montants HT, TVA et TTC |
| Numerotation sequentielle | Format FA-AAAA-NNNN, reinitialisation annuelle, unicite garantie par verrou BDD |
| Gestion des clients | Annuaire clients avec validation SIREN (algorithme de Luhn) et IBAN |
| Multi-taux TVA | Support des taux 0%, 5.5%, 10%, 20%, exoneration, autoliquidation |
| Workflow de statut | DRAFT -> SENT -> ACKNOWLEDGED / REJECTED -> PAID / CANCELLED |
| Generation Factur-X | PDF/A-3 avec XML CII D16B embarque, profil EN 16931 |
| Generation UBL 2.1 | XML pur conforme au profil Peppol BIS Billing 3.0 |
| Transmission PDP | Envoi automatique via l'API de la PDP configuree |
| Reception de factures | Reception des factures entrantes via la PDP |
| Piste d'audit fiable (PAF) | Journalisation immutable de chaque evenement (InvoiceEvent) |
| Archivage legal | Stockage S3 en France, hash SHA-256, conservation 10 ans |
| API REST | API documentee (OpenAPI 3.1) pour integration avec des outils tiers |

### 1.3 Formats supportes

| Format | Norme | Usage |
|---|---|---|
| **Factur-X** | PDF/A-3 + XML CII D16B (EN 16931) | Format recommande pour les PME |
| **UBL 2.1** | XML (EN 16931, Peppol BIS Billing 3.0) | Interoperabilite europeenne |
| **CII D16B** | XML UN/CEFACT | Alternatif a UBL |

### 1.4 Donnees obligatoires presentes dans chaque facture

**Vendeur :** raison sociale, adresse complete, SIREN, numero TVA intracommunautaire,
forme juridique, code NAF/APE.

**Client :** raison sociale, adresse complete, SIREN (si assujetti),
numero TVA intracommunautaire.

**Facture :** numero unique sequentiel, date d'emission, date de livraison/prestation,
devise (EUR par defaut).

**Lignes :** designation, quantite, unite, prix unitaire HT, taux de TVA,
montant TVA, montant HT.

**Totaux :** total HT, total TVA (ventile par taux), total TTC.

**Paiement :** conditions de paiement, date d'echeance, coordonnees bancaires
(IBAN/BIC si virement).

**Mentions legales :** "Autoliquidation" si applicable, reference article d'exoneration,
"TVA non applicable — art. 293 B du CGI" pour les micro-entrepreneurs.

### 1.5 PDP connectee

| PDP | Type | Statut |
|---|---|---|
| **Chorus Pro** | Plateforme publique gratuite (AIFE) | Raccorde et valide en qualification et production |

Le raccordement est realise via la plateforme PISTE en mode API OAuth2.
L'authentification combine un token OAuth2 (flux client_credentials) et un
compte technique Chorus Pro (header cpro-account).

Endpoints utilises :
- `POST /cpro/structures/v1/rechercher` — recherche de structures
- `POST /cpro/factures/v1/soumettre` — soumission de factures (mode SAISIE_API)

---

## 2. Documentation technique de l'API

La documentation complete de l'API est fournie au format OpenAPI 3.1
(fichiers joints : `api-openapi.json` et `api-openapi.yaml`).

### 2.1 Stack technique

| Composant | Technologie |
|---|---|
| Backend | Symfony 7.4 + API Platform 3.3 |
| Base de donnees | PostgreSQL 16 |
| Authentification | JWT (LexikJWTAuthenticationBundle) |
| File d'attente | Symfony Messenger (transport Doctrine/Redis) |
| Frontend | React + TypeScript |

### 2.2 Endpoints principaux

| Methode | Endpoint | Description |
|---|---|---|
| POST | `/api/invoices` | Creer une facture |
| GET | `/api/invoices` | Lister les factures (filtres: statut, date, client) |
| GET | `/api/invoices/{id}` | Consulter une facture |
| PUT | `/api/invoices/{id}` | Modifier une facture (statut DRAFT uniquement) |
| DELETE | `/api/invoices/{id}` | Supprimer une facture (statut DRAFT uniquement) |
| POST | `/api/invoices/{id}/send` | Emettre une facture (transition DRAFT -> SENT) |
| GET | `/api/clients` | Lister les clients |
| POST | `/api/clients` | Creer un client |
| GET | `/api/companies/{id}` | Consulter les informations de l'entreprise |

### 2.3 Securite de l'API

- Authentification par token JWT (RS256, duree 1h)
- Isolation multi-tenant : chaque utilisateur n'accede qu'a ses propres donnees
- Voters Symfony pour le controle d'acces granulaire (VIEW, EDIT, DELETE, SEND)
- HTTPS obligatoire en production
- Rate limiting sur les endpoints publics

---

## 3. Preuve de raccordement PDP — Chorus Pro

### 3.1 Environnement de qualification (sandbox)

Le raccordement a ete valide sur l'environnement de qualification PISTE :
- **Base URL :** `https://sandbox-api.piste.gouv.fr`
- **OAuth URL :** `https://sandbox-oauth.piste.gouv.fr/api/oauth/token`

**Resultats du test soumettreFacture :**
```
codeRetour : 0
libelle : GCU_MSG_01_000
identifiantFactureCPP : 8420532
numeroFacture : 20260000000000000002
statutFacture : DEPOSEE
dateDepot : 2026-04-03
identifiantStructure : 31582210396351
empreinteCertificatDepot : 98q+qTIzpNoJyixTAk5htAw1jMwYMuC9qywhcWyrc9Q=
```

### 3.2 Environnement de production

Le raccordement production est configure avec :
- **Base URL :** `https://api.piste.gouv.fr`
- **OAuth URL :** `https://oauth.piste.gouv.fr/api/oauth/token`
- **SIRET fournisseur :** 93053811100012
- **Compte technique :** actif et fonctionnel

### 3.3 Flux valides

| Operation | Endpoint | Statut |
|---|---|---|
| Authentification OAuth2 | `/api/oauth/token` | OK |
| Recherche de structure | `/cpro/structures/v1/rechercher` | OK |
| Rattachements du compte | `/cpro/utilisateurs/v1/monCompte/recuperer/rattachements` | OK |
| Soumission de facture | `/cpro/factures/v1/soumettre` | OK (codeRetour=0) |

---

## 4. Exemples de factures generees

Les fichiers suivants sont fournis dans le repertoire `exemples-factures/` :

| Fichier | Format | Description |
|---|---|---|
| `facture-simple.xml` | Factur-X (CII D16B) | Facture mono-ligne, TVA 20% |
| `facture-multi-lignes.xml` | Factur-X (CII D16B) | Facture 3 lignes, taux TVA mixtes |
| `facture-exoneration.xml` | Factur-X (CII D16B) | Exoneration TVA art. 261 |
| `facture-ubl-simple.xml` | UBL 2.1 | Facture mono-ligne, TVA 20% |
| `facture-ubl-avoir.xml` | UBL 2.1 | Avoir (credit note) |

---

## 5. Resultats des tests de conformite

### 5.1 Validation XSD

Les factures generees ont ete validees contre les schemas XSD officiels :
- **CII D16B** : schema UN/CEFACT Cross Industry Invoice (EN 16931 CIUS)
- **UBL 2.1** : schema OASIS UBL 2.1 Invoice + credit note

Outil utilise : `horstoeko/factur-x` (validateur PHP) et `factur-x` (validateur Python, Akretion).

### 5.2 Validation EN 16931

Toutes les factures respectent le profil EN 16931 minimum :
- Identifiants vendeur/acheteur presents
- Montants HT, TVA et TTC coherents
- Taux de TVA ventiles par categorie
- Devise conforme (EUR)
- Dates obligatoires presentes

---

## 6. Politique de conservation et d'archivage

### 6.1 Duree de conservation

Conformement a l'article 289 VII du Code General des Impots, toutes les factures
electroniques emises et recues sont conservees pendant une duree minimale de
**10 ans** a compter de la date d'emission.

### 6.2 Modalites de stockage

| Critere | Implementation |
|---|---|
| **Hebergeur** | Scaleway Object Storage (region Paris, datacenter DC3/DC5) |
| **Localisation** | France exclusivement |
| **Format** | Fichier original (PDF/XML) + metadonnees JSON |
| **Organisation** | `{siren}/{annee}/{numero_facture}/` |
| **Integrite** | Hash SHA-256 calcule a l'emission et stocke en base de donnees |
| **Immutabilite** | Versioning S3 active, aucune suppression possible |
| **Redondance** | Replication geographique Scaleway (3 copies minimum) |
| **Sauvegardes** | Sauvegardes quotidiennes de la base PostgreSQL (retention 30 jours) |

### 6.3 Piste d'audit fiable (PAF)

Chaque facture dispose d'un journal d'evenements immutable (entite `InvoiceEvent`) :

| Evenement | Donnees enregistrees |
|---|---|
| `CREATED` | Date, utilisateur, adresse IP |
| `STATUS_CHANGED` | Ancien statut, nouveau statut |
| `TRANSMITTED_TO_PDP` | Nom de la PDP, reference retournee |
| `RECEIVED_BY_PDP` | Statut PDP |
| `ACKNOWLEDGED` | Date d'acceptation |
| `REJECTED` | Motif de rejet |
| `PAID` | Date de paiement |
| `ARCHIVED` | Chemin S3, hash SHA-256 |

Les evenements sont en insertion seule (aucune methode de modification sur l'entite).
Chaque evenement enregistre l'adresse IP et le user-agent du demandeur.

---

## 7. Politique de securite des donnees

### 7.1 Hebergement

| Service | Hebergeur | Localisation | Conformite |
|---|---|---|---|
| Backend applicatif | Fly.io | Paris (region `cdg`) | RGPD, donnees en UE |
| Base de donnees | Fly.io Postgres | Paris | Chiffrement au repos |
| Stockage archives | Scaleway Object Storage | Paris | HDS, RGPD, donnees en France |
| Frontend | Vercel | CDN global | Pas de donnees sensibles |

### 7.2 Chiffrement

| Couche | Methode |
|---|---|
| Transit | HTTPS/TLS 1.3 obligatoire sur tous les endpoints |
| Repos (BDD) | Chiffrement natif PostgreSQL (Fly.io managed) |
| Repos (S3) | Chiffrement AES-256 cote serveur (Scaleway) |
| Tokens JWT | Signature RS256 (cle privee RSA 4096 bits) |
| Mots de passe | Hachage bcrypt (cout 13) |

### 7.3 Controle d'acces

- Authentification par JWT avec expiration (1 heure)
- Isolation multi-tenant stricte (Doctrine QueryExtension)
- Voters Symfony pour le controle d'acces par ressource et par statut
- Aucun acces inter-tenant possible, meme via l'API

### 7.4 Conformite RGPD

| Droit | Implementation |
|---|---|
| Acces (art. 15) | `GET /api/me/data` — export complet des donnees |
| Effacement (art. 17) | `DELETE /api/me` — suppression compte (hors factures : retention legale 10 ans) |
| Portabilite (art. 20) | `GET /api/me/export` — archive ZIP de toutes les factures et donnees |
| Opposition (art. 21) | Desinscription emails marketing dans les parametres |

**Sous-traitants :** Fly.io (hebergement), Scaleway (stockage), Stripe (paiement),
Brevo (emails transactionnels). Tous bases en UE ou avec clauses contractuelles types.

### 7.5 Gestion des incidents

- Monitoring applicatif via Sentry (alertes en temps reel)
- Logs structures JSON en production (Monolog)
- Endpoint de sante `/health` (verification BDD, Redis, S3)
- Notification sous 72h en cas de violation de donnees (art. 33 RGPD)

---

## Annexes

- `api-openapi.json` — Specification OpenAPI 3.1 complete
- `api-openapi.yaml` — Specification OpenAPI 3.1 (format YAML)
- `exemples-factures/` — Factures d'exemple en Factur-X et UBL
