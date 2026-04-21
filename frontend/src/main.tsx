import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'

// Recharger la page si un chunk JS est introuvable apres un deploy
globalThis.addEventListener('error', (e) => {
  if (e.message?.includes('Failed to fetch dynamically imported module') || e.message?.includes('Loading chunk')) {
    globalThis.location.reload();
  }
});
globalThis.addEventListener('unhandledrejection', (e) => {
  const msg = e.reason?.message || '';
  if (msg.includes('Failed to fetch dynamically imported module') || msg.includes('Loading chunk')) {
    globalThis.location.reload();
  }
});

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
