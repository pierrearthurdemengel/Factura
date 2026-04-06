import { useState, useRef, useEffect } from 'react';
import './StatusDropdown.css';

interface Action {
  label: string;
  icon: string;
  onClick: () => void;
  danger?: boolean;
}

interface StatusDropdownProps {
  statusLabel: string;
  statusClass: string;
  disabled?: boolean;
  actions: Action[];
}

export default function StatusDropdown({ statusLabel, statusClass, disabled, actions }: StatusDropdownProps) {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className="status-dropdown-container" ref={containerRef}>
      <button 
        className={`app-badge ${statusClass} status-dropdown-trigger ${disabled ? 'disabled' : ''} ${isOpen ? 'active' : ''}`}
        onClick={() => !disabled && actions.length > 0 && setIsOpen(!isOpen)}
        disabled={disabled || actions.length === 0}
      >
        {statusLabel}
        {actions.length > 0 && (
          <svg className="status-dropdown-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        )}
      </button>

      {isOpen && actions.length > 0 && (
        <div className="status-dropdown-menu">
          <div className="status-dropdown-header">Modifier le statut</div>
          {actions.map((action, idx) => (
            <button 
              key={idx} 
              className={`status-dropdown-item ${action.danger ? 'danger' : ''}`}
              onClick={() => {
                setIsOpen(false);
                action.onClick();
              }}
            >
              <span className="status-dropdown-item-icon">{action.icon}</span>
              {action.label}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
