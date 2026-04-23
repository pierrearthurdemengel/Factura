// Fichiers de traduction pour l'internationalisation.
// Chaque cle correspond a un identifiant utilise dans les composants via FormattedMessage ou useIntl.
// Les locales non-francaises sont chargees dynamiquement pour reduire le bundle initial.

export interface Messages {
  [key: string]: string;
}

// Francais (langue par defaut) — toujours bundle
export const fr: Messages = {
  'nav.invoices': 'Factures',
  'nav.quotes': 'Devis',
  'nav.clients': 'Clients',
  'nav.settings': 'Parametres',
  'nav.dashboard': 'Tableau de bord',
  'nav.search': 'Rechercher...',
  'dashboard.title': 'Tableau de bord',
  'dashboard.invoicesThisMonth': 'Factures du mois',
  'dashboard.revenueHt': 'CA HT du mois',
  'dashboard.pending': 'En attente',
  'dashboard.treasury': 'Tresorerie previsionnelle',
  'dashboard.recentInvoices': 'Dernieres factures',
  'dashboard.activityFeed': 'Fil d\'actualite',
  'dashboard.suggestions': 'Suggestions',
  'dashboard.consolidated': 'Vue consolidee',
  'dashboard.activeCompany': 'Entreprise active',
  'invoice.number': 'Numero',
  'invoice.client': 'Client',
  'invoice.date': 'Date',
  'invoice.amount': 'Montant TTC',
  'invoice.status': 'Statut',
  'invoice.draft': 'Brouillon',
  'invoice.sent': 'Envoyee',
  'invoice.acknowledged': 'Acceptee',
  'invoice.rejected': 'Rejetee',
  'invoice.paid': 'Payee',
  'invoice.cancelled': 'Annulee',
  'invoice.expired': 'Expire',
  'invoice.invoiced': 'Facture',
  'invoice.create': 'Creer une facture',
  'invoice.download.pdf': 'Telecharger PDF',
  'invoice.download.facturx': 'Telecharger Factur-X',
  'invoice.download.ubl': 'Telecharger UBL',
  'client.name': 'Raison sociale',
  'client.siren': 'SIREN',
  'client.address': 'Adresse',
  'client.postalCode': 'Code postal',
  'client.city': 'Ville',
  'client.new': 'Nouveau client',
  'client.delete': 'Supprimer',
  'client.search': 'Rechercher un client...',
  'settings.title': 'Parametres',
  'settings.company': 'Entreprise',
  'settings.customization': 'Personnalisation',
  'settings.reminders': 'Relances',
  'settings.integrations': 'Integrations',
  'settings.factoring': 'Affacturage',
  'settings.billing': 'Facturation',
  'settings.save': 'Enregistrer',
  'settings.saving': 'Enregistrement...',
  'quote.new': 'Nouveau devis',
  'quote.empty.title': 'Aucun devis',
  'quote.empty.description': 'Creez votre premier devis pour commencer.',
  'quote.loadError': 'Impossible de charger la liste des devis.',
  'common.all': 'Tous',
  'common.cancel': 'Annuler',
  'common.confirm': 'Confirmer',
  'common.delete': 'Supprimer',
  'common.edit': 'Modifier',
  'common.close': 'Fermer',
  'common.loading': 'Chargement...',
  'common.noData': 'Aucune donnee',
  'common.export': 'Exporter',
  'currency.eur': 'EUR',
  'format.date': '{date, date, medium}',
  'format.number': '{value, number}',
  'format.currency': '{value, number, ::currency/EUR}',
};

// Imports dynamiques pour les locales non-francaises
const localeLoaders: Record<string, () => Promise<{ default: Messages }>> = {
  it: () => import('./locales/it'),
  de: () => import('./locales/de'),
  es: () => import('./locales/es'),
  pl: () => import('./locales/pl'),
  nl: () => import('./locales/nl'),
};

// Cache des locales deja chargees
const loadedLocales: Record<string, Messages> = { fr };

export async function loadLocaleMessages(locale: string): Promise<Messages> {
  if (loadedLocales[locale]) return loadedLocales[locale];
  const loader = localeLoaders[locale];
  if (!loader) return fr;
  const mod = await loader();
  loadedLocales[locale] = mod.default;
  return mod.default;
}

// Langues supportees avec libelles
export const SUPPORTED_LOCALES = [
  { code: 'fr', label: 'Francais', flag: '\u{1F1EB}\u{1F1F7}' },
  { code: 'it', label: 'Italiano', flag: '\u{1F1EE}\u{1F1F9}' },
  { code: 'de', label: 'Deutsch', flag: '\u{1F1E9}\u{1F1EA}' },
  { code: 'es', label: 'Espanol', flag: '\u{1F1EA}\u{1F1F8}' },
  { code: 'pl', label: 'Polski', flag: '\u{1F1F5}\u{1F1F1}' },
  { code: 'nl', label: 'Nederlands', flag: '\u{1F1F3}\u{1F1F1}' },
] as const;
