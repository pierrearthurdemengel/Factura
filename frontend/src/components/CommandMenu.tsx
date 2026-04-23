import { useEffect, useState, useRef, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { getInvoices, type Invoice } from '../api/factura';
import Fuse from 'fuse.js';
import './CommandMenu.css';

export default function CommandMenu({ isOpen, onClose }: Readonly<{ isOpen: boolean, onClose: () => void }>) {
  const [query, setQuery] = useState('');
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const navigate = useNavigate();
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (isOpen) {
      setQuery(''); // eslint-disable-line react-hooks/set-state-in-effect
      getInvoices({ itemsPerPage: '50' })
        .then(res => setInvoices(res.data['hydra:member']))
        .catch(() => setInvoices([]));

      setTimeout(() => inputRef.current?.focus(), 50);
    }
  }, [isOpen]);

  const fuse = useMemo(() => new Fuse(invoices, {
    keys: [
      { name: 'number', weight: 0.5 },
      { name: 'buyer.name', weight: 0.4 },
      { name: 'totalIncludingTax', weight: 0.1 }
    ],
    threshold: 0.45, // Haute tolérance aux fautes de frappes
    ignoreLocation: true,
  }), [invoices]);

  const filteredInvoices = query.trim().length > 0
    ? fuse.search(query).map(res => res.item)
    : [];

  if (!isOpen) return null;

  const handleAction = (path: string) => {
    navigate(path);
    onClose();
  };

  return (
    <div className="command-overlay" onClick={onClose} onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClose(); } }} role="button" tabIndex={0}>
      <div className="command-dialog" onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()} role="presentation">
        <div className="command-header">
          <svg className="command-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input
            ref={inputRef}
            className="command-input"
            placeholder="Rechercher une facture ou naviguer..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
          <button className="command-esc" onClick={onClose}>ECHAP</button>
        </div>

        <div className="command-body">
          {query.trim().length > 0 && filteredInvoices.length > 0 && (
            <div className="command-group">
              <h4 className="command-group-title">Factures trouvees</h4>
              {filteredInvoices.slice(0, 5).map(inv => (
                <button key={inv.id} className="command-item" onClick={() => handleAction(`/invoices/${inv.id}`)}>
                  <span className="command-item-icon">📄</span>
                  <div className="command-item-content">
                    <span className="command-item-title">{inv.number || 'Brouillon'}</span>
                    <span className="command-item-subtitle">{inv.buyer?.name} - {inv.totalIncludingTax} {inv.currency || 'EUR'}</span>
                  </div>
                </button>
              ))}
            </div>
          )}

          {(!query || 'nouvelle facture creer'.includes(query.toLowerCase())) && (
            <div className="command-group">
              <h4 className="command-group-title">Actions rapides</h4>
              <button className="command-item" onClick={() => handleAction('/invoices/new')}>
                <span className="command-item-icon" style={{ background: 'var(--success-bg)', color: 'var(--success)' }}>✨</span>
                <span className="command-item-title">Creer une facture</span>
              </button>
            </div>
          )}

          <div className="command-group">
            <h4 className="command-group-title">Navigation</h4>
            {(!query || 'tableau bord accueil dashboard'.includes(query.toLowerCase())) && (
              <button className="command-item" onClick={() => handleAction('/')}>
                <span className="command-item-icon" style={{ background: 'var(--social-bg)' }}>📊</span>
                <span className="command-item-title">Tableau de bord</span>
              </button>
            )}
            {(!query || 'toutes factures documents'.includes(query.toLowerCase())) && (
              <button className="command-item" onClick={() => handleAction('/invoices')}>
                <span className="command-item-icon" style={{ background: 'var(--social-bg)' }}>📁</span>
                <span className="command-item-title">Toutes les factures</span>
              </button>
            )}
            {(!query || 'clients repertoire contacts'.includes(query.toLowerCase())) && (
              <button className="command-item" onClick={() => handleAction('/clients')}>
                <span className="command-item-icon" style={{ background: 'var(--social-bg)' }}>👥</span>
                <span className="command-item-title">Base Clients</span>
              </button>
            )}
            {(!query || 'parametres settings entreprise configurer'.includes(query.toLowerCase())) && (
              <button className="command-item" onClick={() => handleAction('/settings')}>
                <span className="command-item-icon" style={{ background: 'var(--social-bg)' }}>⚙️</span>
                <span className="command-item-title">Parametres du compte</span>
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
