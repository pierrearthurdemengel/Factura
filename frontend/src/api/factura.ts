import axios from 'axios';
import { startProgress, doneProgress } from '../utils/progress';
import { invalidateCache } from '../utils/apiCache';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080/api';

// Client API Ma Facture Pro
const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/ld+json',
    Accept: 'application/ld+json',
  },
  // Permet l'envoi des cookies httpOnly (refresh token)
  withCredentials: true,
});

// Intercepteur pour ajouter le JWT d'acces + progress tracking
api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('jwt_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  startProgress();

  // Invalidate related cache on mutations
  if (config.method && ['post', 'put', 'patch', 'delete'].includes(config.method)) {
    const url = config.url || '';
    if (url.includes('/invoices')) invalidateCache('/invoices');
    if (url.includes('/clients')) invalidateCache('/clients');
    if (url.includes('/quotes')) invalidateCache('/quotes');
    if (url.includes('/companies')) invalidateCache('/companies');
  }

  return config;
});

// Track response/error for progress bar
api.interceptors.response.use(
  (response) => { doneProgress(); return response; },
  (error) => { doneProgress(); return Promise.reject(error); },
);

// Verrou pour eviter les appels concurrents de refresh
let isRefreshing = false;
let refreshSubscribers: Array<(token: string) => void> = [];

function onTokenRefreshed(newToken: string) {
  refreshSubscribers.forEach((cb) => cb(newToken));
  refreshSubscribers = [];
}

function addRefreshSubscriber(callback: (token: string) => void) {
  refreshSubscribers.push(callback);
}

// Intercepteur pour gerer le renouvellement automatique du JWT
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;

    // Si 401 et pas deja en train de refresh, tenter un renouvellement
    if (
      error.response?.status === 401 &&
      !originalRequest._retry &&
      !originalRequest.url?.includes('/auth/login') &&
      !originalRequest.url?.includes('/auth/refresh')
    ) {
      if (isRefreshing) {
        // Si un refresh est deja en cours, mettre la requete en attente
        return new Promise((resolve) => {
          addRefreshSubscriber((newToken: string) => {
            originalRequest.headers.Authorization = `Bearer ${newToken}`;
            resolve(api(originalRequest));
          });
        });
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        // Appeler l'endpoint de refresh (le cookie httpOnly est envoye automatiquement)
        const response = await api.post('/auth/refresh');
        const { token } = response.data;

        sessionStorage.setItem('jwt_token', token);
        originalRequest.headers.Authorization = `Bearer ${token}`;

        onTokenRefreshed(token);

        return api(originalRequest);
      } catch {
        // Le refresh a echoue — deconnecter l'utilisateur
        sessionStorage.removeItem('jwt_token');
        globalThis.location.href = '/login';
        throw error;
      } finally {
        isRefreshing = false;
      }
    }

    throw error;
  },
);

// Types
export interface Invoice {
  '@id': string;
  id: string;
  number: string | null;
  status: string;
  issueDate: string;
  dueDate: string | null;
  currency: string;
  totalExcludingTax: string;
  totalTax: string;
  totalIncludingTax: string;
  buyer: Client;
  seller: Company;
  lines: InvoiceLine[];
  legalMention: string | null;
  paymentTerms: string | null;
  pdpReference: string | null;
  sourceQuote: string | null;
  createdAt: string;
}

export interface InvoiceLine {
  id: string;
  position: number;
  description: string;
  quantity: string;
  unit: string;
  unitPriceExcludingTax: string;
  vatRate: string;
  lineAmount: string;
  vatAmount: string;
}

export interface Client {
  '@id': string;
  id: string;
  name: string;
  siren: string | null;
  vatNumber: string | null;
  addressLine1: string;
  postalCode: string;
  city: string;
  countryCode: string;
}

export interface Company {
  '@id': string;
  id: string;
  name: string;
  siren: string;
  siret: string | null;
  vatNumber: string | null;
  legalForm: string;
  nafCode: string | null;
  addressLine1: string;
  addressLine2: string | null;
  postalCode: string;
  city: string;
  countryCode: string;
  iban: string | null;
  bic: string | null;
  defaultPdp: string | null;
}

export interface UserProfile {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
  roles: string[];
}

// Fonctions API — Authentification
export const login = async (email: string, password: string) => {
  const response = await api.post('/auth/login', { email, password });
  const { token } = response.data;
  sessionStorage.setItem('jwt_token', token);
  return token;
};

export const refreshToken = async (): Promise<string> => {
  const response = await api.post('/auth/refresh');
  const { token } = response.data;
  sessionStorage.setItem('jwt_token', token);
  return token;
};

export const logout = async () => {
  try {
    await api.post('/auth/logout');
  } finally {
    sessionStorage.removeItem('jwt_token');
  }
};

export const getMe = async () => {
  return api.get<UserProfile>('/me');
};

export const register = async (data: Record<string, string>) => {
  return api.post('/register', data);
};

export const getInvoices = async (params?: Record<string, string>) => {
  return api.get<{ 'hydra:member': Invoice[] }>('/invoices', { params });
};

export const getInvoice = async (id: string) => {
  return api.get<Invoice>(`/invoices/${id}`);
};

export const createInvoice = async (data: Record<string, unknown>) => {
  return api.post<Invoice>('/invoices', data);
};

export const updateInvoice = async (id: string, data: Record<string, unknown>) => {
  return api.put<Invoice>(`/invoices/${id}`, data);
};

export const deleteInvoice = async (id: string) => {
  return api.delete(`/invoices/${id}`);
};

export const sendInvoice = async (id: string) => {
  return api.post<Invoice>(`/invoices/${id}/send`, {});
};

export const cancelInvoice = async (id: string) => {
  return api.post<Invoice>(`/invoices/${id}/cancel`, {});
};

export const payInvoice = async (id: string) => {
  return api.post<Invoice>(`/invoices/${id}/pay`, {});
};

export const getClients = async () => {
  return api.get<{ 'hydra:member': Client[] }>('/clients');
};

export const createClient = async (data: Partial<Client>) => {
  return api.post<Client>('/clients', data);
};

export const updateClient = async (id: string, data: Partial<Client>) => {
  return api.put<Client>(`/clients/${id}`, data);
};

export const deleteClient = async (id: string) => {
  return api.delete(`/clients/${id}`);
};

export const getCompany = async () => {
  return api.get<Company>('/companies/me');
};

export const updateCompany = async (id: string, data: Partial<Company>) => {
  return api.put<Company>(`/companies/${id}`, data);
};

// Telecharge le PDF Factur-X (PDF/A-3 avec XML CII embarque) d'une facture
export const downloadPdf = async (id: string) => {
  return api.get(`/invoices/${id}/pdf`, {
    responseType: 'blob',
    headers: { Accept: 'application/pdf' },
  });
};

// Telecharge le XML Factur-X (CII D16B) d'une facture
export const downloadFacturX = async (id: string) => {
  return api.get(`/invoices/${id}/download/facturx`, {
    responseType: 'blob',
    headers: { Accept: 'application/xml' },
  });
};

// Telecharge le XML UBL 2.1 d'une facture
export const downloadUbl = async (id: string) => {
  return api.get(`/invoices/${id}/download/ubl`, {
    responseType: 'blob',
    headers: { Accept: 'application/xml' },
  });
};

// Types pour les evenements PAF
export interface InvoiceEvent {
  id: string;
  eventType: string;
  metadata: Record<string, string>;
  occurredAt: string;
}

// Recupere les evenements PAF d'une facture
export const getInvoiceEvents = async (id: string) => {
  return api.get<InvoiceEvent[]>(`/invoices/${id}/events`);
};

// Types pour les devis
export interface Quote {
  '@id': string;
  id: string;
  number: string | null;
  status: string;
  issueDate: string;
  validUntil: string | null;
  totalExcludingTax: string;
  totalTax: string;
  totalIncludingTax: string;
  buyer: Client;
  seller: Company;
  lines: QuoteLine[];
  legalMention: string | null;
  paymentTerms: string | null;
  convertedInvoice: string | null;
  depositPercent: number | null;
  depositAmount: string | null;
  createdAt: string;
}

export interface QuoteLine {
  id: string;
  position: number;
  description: string;
  quantity: string;
  unit: string;
  unitPriceExcludingTax: string;
  vatRate: string;
  lineAmount: string;
  vatAmount: string;
}

// Fonctions API devis
export const getQuotes = async (params?: Record<string, string>) => {
  return api.get<{ 'hydra:member': Quote[] }>('/quotes', { params });
};

export const getQuote = async (id: string) => {
  return api.get<Quote>(`/quotes/${id}`);
};

export const createQuote = async (data: Record<string, unknown>) => {
  return api.post<Quote>('/quotes', data);
};

export const sendQuote = async (id: string) => {
  return api.post<Quote>(`/quotes/${id}/send`, {});
};

export const convertQuoteToInvoice = async (id: string) => {
  return api.post<Invoice>(`/quotes/${id}/convert`, {});
};

// Retourne l'URL du portail de facturation Stripe
export const getStripePortalUrl = async () => {
  return api.get<{ url: string }>('/subscription/portal');
};

export default api;
