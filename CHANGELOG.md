# Changelog

Toutes les modifications notables sont documentees ici.
Format : [Keep a Changelog](https://keepachangelog.com)
Versioning : [SemVer](https://semver.org)

## [Unreleased]

## [1.0.0] - 2026-04-04

### Added
- Entites Doctrine completes : User, Company, Client, Product, Invoice, InvoiceLine, InvoiceEvent, Subscription
- Authentification JWT (RS256, refresh token, expiration 1h)
- Isolation multi-tenant stricte (QueryExtension + Voters Symfony)
- API REST complete via API Platform 3 (CRUD invoices, clients, companies, products)
- Validation conforme DGFiP : SIREN (Luhn), IBAN, mentions legales obligatoires
- Numerotation sequentielle FA-AAAA-NNNN avec verrou BDD et reinitialisation annuelle
- Workflow Symfony : cycle de vie facture (DRAFT, SENT, ACKNOWLEDGED, REJECTED, PAID, CANCELLED)
- Piste d'audit fiable (PAF) : journal immutable InvoiceEvent
- Generation Factur-X (PDF/A-3 + XML CII D16B, profil EN 16931)
- Generation UBL 2.1 (Peppol BIS Billing 3.0)
- Generation CII D16B standalone
- Lecteur Factur-X pour factures entrantes
- Archivage legal S3 (Scaleway France, SHA-256, versioning, 10 ans)
- Integration PDP Chorus Pro via PISTE OAuth2 (soumission + suivi statut)
- Abonnements Stripe (Free, Pro, Equipe) avec webhooks et quotas
- Frontend React complet : dashboard, liste factures, creation, detail, clients, parametres
- Landing page publique avec tarifs et fonctionnalites
- Dossier de candidature DGFiP (7 documents)
- Exemples de factures : Factur-X simple, multi-lignes, exoneration + UBL simple, avoir
- Documentation API OpenAPI 3.1
- CI GitHub Actions (PHPStan level 8, PHPUnit, PHP CS Fixer, ESLint, build frontend)
- Docker (PHP 8.3, PostgreSQL 16, Nginx, Redis)
