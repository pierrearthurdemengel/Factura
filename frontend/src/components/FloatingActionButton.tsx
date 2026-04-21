import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import './FloatingActionButton.css';

interface FABProps {
  onOpenSearch: () => void;
}

export default function FloatingActionButton({ onOpenSearch }: Readonly<FABProps>) {
  const [isOpen, setIsOpen] = useState(false);
  const navigate = useNavigate();

  const handleAction = (cb: () => void) => {
    setIsOpen(false);
    cb();
  };

  return (
    <div className="fab-container">
      <div className={`fab-overlay ${isOpen ? 'open' : ''}`} onClick={() => setIsOpen(false)} />
      
      <div className={`fab-menu ${isOpen ? 'open' : ''}`}>
        <button className="fab-item" onClick={() => handleAction(() => navigate('/invoices/new'))}>
          <span className="fab-label">Nouvelle Facture</span>
          <div className="fab-icon-sm">📄</div>
        </button>
        <button className="fab-item" onClick={() => handleAction(() => navigate('/clients'))}>
          <span className="fab-label">Clients</span>
          <div className="fab-icon-sm">👥</div>
        </button>
        <button className="fab-item" onClick={() => handleAction(() => onOpenSearch())}>
          <span className="fab-label">Recherche</span>
          <div className="fab-icon-sm">🔍</div>
        </button>
      </div>

      <button className={`fab-main ${isOpen ? 'active' : ''}`} onClick={() => setIsOpen(!isOpen)}>
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
      </button>
    </div>
  );
}
