# Factura — Audit & Todo List

Audit complet du frontend realise le 15/04/2026.
2 commits de corrections pushes (recharts -> SVG, branchement API des features cassees).

---

## Corrections effectuees

- [x] Dashboard : remplacement recharts par SVG natif (React 19 compat, -346 kB bundle)
- [x] OnboardingWizard : sauvegarde de toutes les donnees (vatRegime, exercice, couleurs, logo, payment terms)
- [x] Settings : personnalisation et relances branchees sur API (avant : faux message succes)
- [x] Pricing : boutons CTA redirigent vers /register (avant : aucune action)
- [x] VoiceAssistant : fix re-renders infinis (dep displayedText retiree)
- [x] CommandMenu : utilise inv.currency au lieu de EUR hardcode
- [x] Network : stub graphique remplace par affichage reel des stats

---

## Todo — Priorite haute (fonctionnalites manquantes)

### 1. Acomptes sur devis (Phase 13)
- [ ] Ajouter un champ `depositPercentage` et `depositAmount` dans QuoteCreate
- [ ] Afficher le montant de l'acompte dans QuoteDetail
- [ ] Permettre la generation d'une facture d'acompte partielle depuis un devis
- [ ] Ajouter les types correspondants dans `api/factura.ts` (QuoteLine.deposit?)
- **Fichiers** : `QuoteCreate.tsx`, `QuoteDetail.tsx`, `api/factura.ts`

### 2. Banking — connexion bancaire reelle (Phase 17)
- [ ] Remplacer le wizard 3 etapes mockup par une integration GoCardless/Nordigen
- [ ] Etape 1 : appel API `/banking/connect` pour obtenir un lien d'autorisation
- [ ] Etape 2 : redirection vers la banque (OAuth2), callback avec le code
- [ ] Etape 3 : confirmation et premier sync des transactions
- [ ] Gerer l'etat de connexion (connecte / deconnecte / en erreur)
- [ ] Ajouter un bouton "Deconnecter la banque" dans l'onglet Connexion
- **Fichiers** : `Banking.tsx`, `api/factura.ts`

### 3. Declarations — calendrier fiscal dynamique (Phase 21)
- [ ] Remplacer les deadlines hardcodees par un appel API `/tax/deadlines`
- [ ] Adapter l'interface `Deadline` aux donnees reelles du backend
- [ ] Ajouter un indicateur visuel pour les echeances passees non remplies
- [ ] Coherence entre le calendrier et les onglets TVA/URSSAF (memes donnees API)
- **Fichiers** : `Declarations.tsx`, `api/factura.ts`

---

## Todo — Priorite moyenne (ameliorations fonctionnelles)

### 4. Settings — plan actuel dynamique
- [ ] Ajouter `getSubscriptionStatus()` dans `api/factura.ts`
- [ ] Remplacer le texte hardcode "Plan actuel : Gratuit" par le vrai plan
- [ ] Afficher les limites restantes (ex: 12/30 factures ce mois)
- [ ] Synchroniser `currentPlan` dans l'onglet Facturation avec le backend
- **Fichiers** : `Settings.tsx`, `api/factura.ts`

### 5. Experts — donnees dynamiques
- [ ] Remplacer les 5 profils hardcodes par un appel API `/partners/experts`
- [ ] Ajouter loading skeleton et etat d'erreur
- [ ] Ajouter la recherche/filtre par specialite
- **Fichiers** : `Experts.tsx`, `api/factura.ts`

### 6. Simulators — bareme fiscal externalisable
- [ ] Extraire les baremes fiscaux (IR, URSSAF, abattements) dans un fichier de config JSON
- [ ] Ou les charger depuis un endpoint API `/tax/rates/{year}`
- [ ] Ajouter un avertissement clair "Simulation indicative, non contractuelle"
- **Fichiers** : `Simulators.tsx`

### 7. Unpaid — historique de relances
- [ ] Peupler le champ `reminders` depuis l'API (`/invoices/{id}/reminders`)
- [ ] Afficher la chronologie des relances envoyees pour chaque facture
- [ ] Ajouter un bouton "Envoyer une relance" directement depuis la liste
- **Fichiers** : `Unpaid.tsx`, `api/factura.ts`

### 8. InvoiceDetail — commission affacturage configurable
- [ ] Lire le taux de commission depuis les settings ou l'API au lieu de 5% hardcode
- [ ] Afficher le taux applique dans le bouton de financement
- **Fichiers** : `InvoiceDetail.tsx`

### 9. Error handling — unifier les catches silencieux
- [ ] QuoteList.tsx : `.catch(() => {})` -> toast d'erreur
- [ ] QuoteCreate.tsx : `.catch(() => setClients([]))` -> toast + fallback
- [ ] ClientList.tsx : catches silencieux lignes 82, 85
- [ ] Accounting.tsx : catches silencieux lignes 33, 76-78
- [ ] AccountantPortal.tsx : 6 catches silencieux (lignes 81-233)
- [ ] Banking.tsx : catch silencieux ligne 43
- [ ] CommandMenu.tsx : `.catch(() => {})` ligne 18
- [ ] Network.tsx : catches silencieux sur stats/referral
- **Regle** : tout `.catch(() => {})` doit au minimum logger ou afficher un toast

---

## Todo — Priorite basse (qualite & polish)

### 10. Code splitting / lazy loading
- [ ] Wrapper les pages lourdes avec `React.lazy()` + `Suspense` :
  - Guide.tsx (1992 lignes)
  - ApiDocs.tsx (documentation volumineuse)
  - Accounting.tsx
  - InvoiceCreate.tsx (formulaire complexe)
- [ ] Configurer les splitChunks dans vite.config.ts
- [ ] Objectif : chunk principal < 500 kB

### 11. Internationalisation (react-intl)
- [ ] react-intl est installe et le provider existe (IntlProvider, useLocale)
- [ ] Mais aucune page n'utilise `<FormattedMessage>` ou `intl.formatMessage()`
- [ ] Tout le texte est en francais hardcode
- [ ] Extraire les chaines dans des fichiers de messages (fr.json, en.json)
- [ ] Priorite : commencer par les pages publiques (Landing, Pricing, Guide)

### 12. Error boundary global
- [ ] Creer un composant `ErrorBoundary` React (class component)
- [ ] Wrapper `<AnimatedAppCore>` dans `App.tsx`
- [ ] Afficher une page d'erreur propre avec bouton "Recharger"

### 13. Tests E2E Cypress
- [ ] Cypress est installe mais aucun test n'existe
- [ ] Ecrire les tests pour les flux critiques :
  - [ ] Login / Register
  - [ ] Creation de facture
  - [ ] Creation de devis + conversion en facture
  - [ ] Changement de statut facture (envoyer, payer, annuler)
  - [ ] Navigation dashboard
  - [ ] Export PDF

### 14. Accessibilite
- [ ] Ajouter `aria-label` aux boutons icones (theme, langue, hamburger dans NavBar)
- [ ] Verifier la navigation clavier sur tous les formulaires
- [ ] Ajouter `role="alert"` aux messages d'erreur/succes
- [ ] Verifier les contrastes de couleur (badges de statut)

### 15. ApiDocs — bouton "Essayer" fonctionnel
- [ ] Le bouton "Essayer" ne fait qu'un `console.log`
- [ ] Implementer un vrai appel API avec affichage de la reponse
- [ ] Ajouter un bloc de reponse JSON avec syntaxe coloree
- **Fichiers** : `ApiDocs.tsx`

---

## Etat des pages par phase du plan

| Phase | Feature | Page | Statut |
|-------|---------|------|--------|
| 13 | Devis & acomptes | QuoteList/Create/Detail | OK (acomptes manquants) |
| 14 | Personnalisation PDF | Settings | OK (corrige) |
| 15 | Relances automatiques | Settings + InvoiceDetail | OK (corrige) |
| 16 | Multi-entite | NavBar | OK |
| 17 | Synchro bancaire | Banking | UI mockup |
| 18 | Justificatifs + OCR | CameraScanner | OK |
| 19 | Comptabilite auto | Accounting | OK |
| 20 | Portail comptable | AccountantPortal | OK |
| 21 | Declarations fiscales | Declarations | Calendrier hardcode |
| 22 | Dashboard + tresorerie | Dashboard | OK (corrige) |
| 23 | IA conversationnelle | AssistantPage | OK |
| 24 | API publique + Marketplace | ApiSettings + ApiDocs | OK |
| 25 | PWA mobile | vite-plugin-pwa | OK |
| 26 | Creation entreprise | CompanyCreation | OK |
| 27 | Pilotage IA externe | AutopilotConfig | OK |
| 28 | Reseau paiement | Network | OK (corrige) |
| 29 | Affacturage instantane | InvoiceDetail | OK (commission hardcodee) |
| 30 | Intelligence collective | Benchmarks | OK |
| 31 | White-label | AccountantPortal | OK |
| 32 | Hub administratif | AdminHub | OK |
| 33 | Pricing inverse | Pricing | OK (corrige) |

---

## Resume

- **Pages totales** : 31
- **Pages 100% fonctionnelles** : 25
- **Pages corrigees dans cet audit** : 7
- **Pages avec features manquantes** : 3 (Banking, Declarations, Quotes/acomptes)
- **Pages avec donnees hardcodees** : 3 (Experts, Simulators, Settings/plan)
