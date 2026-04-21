import { motion } from 'framer-motion';
import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';

export default function CollaboratorCursor() {
  const [position, setPosition] = useState({ x: -100, y: -100 });
  const location = useLocation();

  useEffect(() => {
    // Only spawn Alice in Kanban or Dashboard to simulate collaboration!
    if (location.pathname !== '/invoices' && location.pathname !== '/') return;

    const moveAlice = () => {
      const rx = (globalThis.innerWidth * 0.1) + Math.random() * (globalThis.innerWidth * 0.8);
      const ry = (globalThis.innerHeight * 0.2) + Math.random() * (globalThis.innerHeight * 0.6);
      setPosition({ x: rx, y: ry });
    };

    const interval = setInterval(moveAlice, 4500);
    setTimeout(moveAlice, 1000); // Initial spawn delay
    
    return () => clearInterval(interval);
  }, [location.pathname]);

  if (location.pathname !== '/invoices' && location.pathname !== '/') return null;

  return (
    <motion.div
      initial={{ x: -100, y: -100, opacity: 0 }}
      animate={{ x: position.x, y: position.y, opacity: 1 }}
      transition={{ type: 'spring', stiffness: 50, damping: 22, mass: 1.5 }}
      style={{
        position: 'fixed',
        top: 0, left: 0,
        pointerEvents: 'none',
        zIndex: 99998, // Just under the player's CustomCursor
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'flex-start'
      }}
    >
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ filter: 'drop-shadow(0 4px 6px rgba(0,0,0,0.1))' }}>
        <path d="M5.5 3L20 12L12 14L9.5 22L5.5 3Z" fill="#F43F5E" stroke="var(--bg)" strokeWidth="2.5" strokeLinejoin="round"/>
      </svg>
      <div style={{
        background: '#F43F5E',
        color: '#FFF',
        fontSize: '11px',
        fontWeight: 700,
        padding: '3px 10px',
        borderRadius: '12px',
        borderTopLeftRadius: '0px',
        marginLeft: '12px',
        boxShadow: '0 4px 10px rgba(244, 63, 94, 0.4)'
      }}>
        Alice (Comptable)
      </div>
    </motion.div>
  );
}
