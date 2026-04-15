import { useState, useEffect } from 'react';
import api from '../api/factura';
import '../pages/AppLayout.css';

interface Expert {
  name: string;
  specialty: string;
  description: string;
  location: string;
  email: string;
  website: string;
}

// Donnees par defaut si l'API n'est pas disponible
const defaultExperts: Expert[] = [
  {
    name: 'Cabinet Dupont & Associes',
    specialty: 'Comptabilite',
    description: 'Expert-comptable specialise freelances et TPE. Accompagnement creation et gestion.',
    location: 'Paris',
    email: 'contact@dupont-compta.fr',
    website: 'https://dupont-compta.fr',
  },
  {
    name: 'Claire Martin — Avocate',
    specialty: 'Juridique',
    description: 'Droit des affaires, recouvrement de creances, contentieux commercial.',
    location: 'Lyon',
    email: 'claire.martin@avocat.fr',
    website: '',
  },
  {
    name: 'Assurance Pro Direct',
    specialty: 'Assurance',
    description: 'RC Pro, protection juridique et assurance cyberrisques pour independants.',
    location: 'France entiere',
    email: 'pro@assurance-direct.fr',
    website: 'https://assurance-direct.fr',
  },
  {
    name: 'Formation Compta Express',
    specialty: 'Formation',
    description: 'Formations certifiantes en comptabilite, fiscalite et gestion de tresorerie.',
    location: 'En ligne',
    email: 'info@compta-express.fr',
    website: 'https://compta-express.fr',
  },
  {
    name: 'Cabinet Leroy Patrimoine',
    specialty: 'Gestion de patrimoine',
    description: 'Optimisation fiscale, placement et strategie patrimoniale pour entrepreneurs.',
    location: 'Bordeaux',
    email: 'contact@leroy-patrimoine.fr',
    website: '',
  },
];

export default function Experts() {
  const [experts, setExperts] = useState<Expert[]>(defaultExperts);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get<{ 'hydra:member': Expert[] }>('/partners')
      .then((res) => {
        const data = res.data['hydra:member'];
        if (data && data.length > 0) setExperts(data);
      })
      .catch(() => {/* Utiliser les donnees par defaut */})
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="app-container" style={{ textAlign: 'left' }}>
      <h1 className="app-page-title">Experts partenaires</h1>
      <p style={{ color: 'var(--text)', marginBottom: '2rem', maxWidth: 650, lineHeight: 1.6 }}>
        Des professionnels de confiance pour vous accompagner dans la gestion
        de votre activite. Contactez-les directement par email ou via leur site.
      </p>

      {loading ? (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem', maxWidth: 700 }}>
          {[1, 2, 3].map((i) => (
            <div key={i} className="app-skeleton app-skeleton-card" />
          ))}
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem', maxWidth: 700 }}>
          {experts.map((expert) => (
            <div
              key={expert.name}
              className="app-card"
              style={{ flexDirection: 'row', gap: '1rem', alignItems: 'flex-start' }}
            >
              <div style={{
                width: 48, height: 48, borderRadius: '50%', background: 'var(--accent-bg)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontWeight: 700, color: 'var(--accent)', fontSize: '1.1rem', flexShrink: 0
              }}>
                {expert.name.charAt(0)}
              </div>
              <div style={{ flex: 1 }}>
                <div style={{ fontWeight: 600, color: 'var(--text-h)', fontSize: '1rem' }}>
                  {expert.name}
                </div>
                <div style={{
                  display: 'inline-block', background: 'var(--accent-bg)', color: 'var(--accent)',
                  padding: '2px 8px', borderRadius: '1rem', fontSize: '0.75rem', fontWeight: 600, marginTop: 4
                }}>
                  {expert.specialty}
                </div>
                <p style={{ margin: '0.5rem 0', fontSize: '0.9rem', color: 'var(--text)' }}>
                  {expert.description}
                </p>
                <div style={{ fontSize: '0.85rem', color: 'var(--text)', display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
                  <span>{expert.location}</span>
                  <a href={`mailto:${expert.email}`} style={{ color: 'var(--accent)', textDecoration: 'none' }}>
                    {expert.email}
                  </a>
                  {expert.website && (
                    <a href={expert.website} target="_blank" rel="noopener noreferrer"
                       style={{ color: 'var(--accent)', textDecoration: 'none' }}>
                      Site web
                    </a>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
