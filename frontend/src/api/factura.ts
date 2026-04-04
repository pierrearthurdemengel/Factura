import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080/api';

// Client API Factura
const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/ld+json',
    Accept: 'application/ld+json',
  },
});

// Intercepteur pour ajouter le token JWT
api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('jwt_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Intercepteur pour gerer le refresh token
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      sessionStorage.removeItem('jwt_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
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

// Fonctions API
export const login = async (email: string, password: string) => {
  const response = await api.post('/auth', { email, password });
  const { token } = response.data;
  sessionStorage.setItem('jwt_token', token);
  return token;
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
  return api.post<Invoice>(`/invoices/${id}/send`);
};

export const cancelInvoice = async (id: string) => {
  return api.post<Invoice>(`/invoices/${id}/cancel`);
};

export const payInvoice = async (id: string) => {
  return api.post<Invoice>(`/invoices/${id}/pay`);
};

export const getClients = async () => {
  return api.get<{ 'hydra:member': Client[] }>('/clients');
};

export const createClient = async (data: Partial<Client>) => {
  return api.post<Client>('/clients', data);
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

export default api;
