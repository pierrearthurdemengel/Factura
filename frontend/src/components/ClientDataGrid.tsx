import { useState } from 'react';
import { type Client } from '../api/factura';
import { useToast } from '../context/ToastContext';

interface ClientDataGridProps {
  clients: Client[];
  onUpdateClient: (id: string, data: Partial<Client>) => Promise<void>;
  onDeleteClient: (id: string) => Promise<void>;
}

export default function ClientDataGrid({ clients, onUpdateClient, onDeleteClient }: ClientDataGridProps) {
  const [colWidths, setColWidths] = useState([200, 150, 200, 100, 150, 80]);
  const { error } = useToast();

  const handleResizeStart = (e: React.MouseEvent, index: number) => {
    e.preventDefault();
    const startX = e.clientX;
    const startW = colWidths[index];

    const handleMouseMove = (moveE: MouseEvent) => {
      const delta = moveE.clientX - startX;
      setColWidths(prev => {
        const next = [...prev];
        next[index] = Math.max(50, startW + delta);
        return next;
      });
    };

    const handleMouseUp = () => {
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
    };

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
  };

  const handleChange = (id: string, field: keyof Client, val: string) => {
    onUpdateClient(id, { [field]: val }).catch(() => error("Erreur de sauvegarde"));
  };

  const gridTemplateColumns = colWidths.map(w => `${w}px`).join(' ');

  return (
    <div style={{ overflowX: 'auto', border: '1px solid var(--border)', borderRadius: '12px', background: 'var(--bg)' }}>
      <div style={{ minWidth: colWidths.reduce((a,b)=>a+b, 0) + 'px' }}>
        
        {/* Header */}
        <div style={{ 
          display: 'grid', 
          gridTemplateColumns, 
          borderBottom: '1px solid var(--border)', 
          background: 'var(--social-bg)', 
          fontWeight: 600, 
          fontSize: '0.85rem', 
          color: 'var(--text)' 
        }}>
          {['Raison Sociale', 'SIREN', 'Adresse', 'CP', 'Ville', 'Actions'].map((title, i) => (
            <div key={i} style={{ display: 'flex', alignItems: 'center', position: 'relative' }}>
              <div style={{ padding: '12px 16px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', flex: 1 }}>
                {title}
              </div>
              <div 
                onMouseDown={(e) => handleResizeStart(e, i)}
                style={{ position: 'absolute', right: 0, top: 0, bottom: 0, width: '4px', cursor: 'col-resize', background: 'transparent' }}
                onMouseEnter={e => (e.currentTarget.style.background = 'var(--accent)')}
                onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
              />
            </div>
          ))}
        </div>

        {/* Body */}
        <div>
          {clients.map(client => (
            <div key={client.id} style={{ display: 'grid', gridTemplateColumns, borderBottom: '1px solid var(--border)' }}>
              
              <div style={{ padding: '0' }} className="grid-cell-container">
                <input 
                  type="text" 
                  defaultValue={client.name} 
                  onBlur={(e) => { if(e.target.value !== client.name) handleChange(client.id, 'name', e.target.value) }}
                  className="grid-inline-input"
                />
              </div>

              <div style={{ padding: '0' }} className="grid-cell-container">
                <input 
                  type="text" 
                  defaultValue={client.siren || ''} 
                  onBlur={(e) => { if(e.target.value !== client.siren) handleChange(client.id, 'siren', e.target.value) }}
                  className="grid-inline-input"
                  placeholder="-"
                />
              </div>

              <div style={{ padding: '0' }} className="grid-cell-container">
                <input 
                  type="text" 
                  defaultValue={client.addressLine1} 
                  onBlur={(e) => { if(e.target.value !== client.addressLine1) handleChange(client.id, 'addressLine1', e.target.value) }}
                  className="grid-inline-input"
                />
              </div>

              <div style={{ padding: '0' }} className="grid-cell-container">
                <input 
                  type="text" 
                  defaultValue={client.postalCode} 
                  onBlur={(e) => { if(e.target.value !== client.postalCode) handleChange(client.id, 'postalCode', e.target.value) }}
                  className="grid-inline-input"
                />
              </div>

              <div style={{ padding: '0' }} className="grid-cell-container">
                <input 
                  type="text" 
                  defaultValue={client.city} 
                  onBlur={(e) => { if(e.target.value !== client.city) handleChange(client.id, 'city', e.target.value) }}
                  className="grid-inline-input"
                />
              </div>

              <div style={{ padding: '8px 16px', display: 'flex', alignItems: 'center' }}>
                <button 
                  onClick={() => onDeleteClient(client.id)}
                  style={{ background: 'transparent', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: '0.8rem', fontWeight: 600 }}
                >
                  Supprimer
                </button>
              </div>

            </div>
          ))}
        </div>
      </div>

      <style>{`
        .grid-inline-input {
          width: 100%;
          height: 100%;
          border: none;
          background: transparent;
          padding: 12px 16px;
          font-family: inherit;
          font-size: 0.9rem;
          color: var(--text-h);
          outline: none;
          box-sizing: border-box;
          transition: background 0.2s;
        }
        .grid-inline-input:hover {
          background: var(--social-bg);
        }
        .grid-inline-input:focus {
          background: var(--bg);
          box-shadow: inset 0 0 0 2px var(--accent);
        }
      `}</style>
    </div>
  );
}
