import { useEffect, useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useToast } from '../context/ToastContext';
import { useAudio } from '../context/AudioScope';
import { motion, AnimatePresence } from 'framer-motion';

export default function VoiceAssistant() {
  const [isListening, setIsListening] = useState(false);
  const [displayedText, setDisplayedText] = useState('');
  const transcriptRef = useRef('');
  const navigate = useNavigate();
  const { info, success } = useToast();
  const audio = useAudio();

  useEffect(() => {
    // @ts-expect-error - API Web Speech non standard selon les navigateurs
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) return;

    const recognition = new SpeechRecognition();
    recognition.lang = 'fr-FR';
    recognition.interimResults = true;
    recognition.continuous = false;

    let isSpaceDown = false;

    recognition.onstart = () => {
      setIsListening(true);
      transcriptRef.current = '';
      setDisplayedText('');
      audio.playPop(); // Haptic feedback
    };

    recognition.onresult = (event: Event & { resultIndex: number; results: SpeechRecognitionResultList }) => {
      let interim = '';
      for (let i = event.resultIndex; i < event.results.length; ++i) {
        if (event.results[i].isFinal) {
          transcriptRef.current += event.results[i][0].transcript;
        } else {
          interim += event.results[i][0].transcript;
        }
      }
      setDisplayedText(transcriptRef.current + interim);
    };

    recognition.onend = () => {
      setIsListening(false);
      isSpaceDown = false;
      const finalWord = transcriptRef.current.toLowerCase() || displayedText.toLowerCase();

      if (!finalWord) return;

      if (finalWord.includes('facture')) {
        audio.playTick();
        success("Demande captée : Création de Facture");
        navigate('/invoices/new');
      } else {
        info(`Assistant: "${finalWord}"`);
      }
    };

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.code === 'Space' && !e.repeat) {
        const target = e.target as HTMLElement;
        const tag = target.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || target.isContentEditable) return;
        
        e.preventDefault();
        if (!isSpaceDown) {
          isSpaceDown = true;
          try { recognition.start(); } catch { /* deja en cours */ }
        }
      }
    };

    const handleKeyUp = (e: KeyboardEvent) => {
      if (e.code === 'Space') {
        const target = e.target as HTMLElement;
        const tag = target.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || target.isContentEditable) return;
        
        isSpaceDown = false;
        try { recognition.stop(); } catch { /* deja en cours */ }
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      window.removeEventListener('keyup', handleKeyUp);
      recognition.abort();
    };
  }, [audio, navigate, success, info, displayedText]);

  return (
    <AnimatePresence>
      {isListening && (
        <motion.div
           initial={{ opacity: 0, y: 50, scale: 0.9, x: '-50%' }}
           animate={{ opacity: 1, y: 0, scale: 1, x: '-50%' }}
           exit={{ opacity: 0, y: 50, scale: 0.9, x: '-50%' }}
           transition={{ type: 'spring', damping: 25, stiffness: 300 }}
           style={{
             position: 'fixed',
             bottom: '40px',
             left: '50%',
             background: 'var(--social-bg)',
             border: '1px solid var(--border)',
             boxShadow: '0 20px 40px rgba(0,0,0,0.4)',
             padding: '16px 24px',
             borderRadius: '50px',
             display: 'flex',
             alignItems: 'center',
             gap: '20px',
             zIndex: 9999,
             backdropFilter: 'blur(10px)'
           }}
        >
           {/* Visualizer bars */}
           <div style={{ display: 'flex', gap: '4px', alignItems: 'center', height: '24px' }}>
             {[1, 2, 3].map(i => (
               <motion.div
                 key={i}
                 animate={{ height: ['8px', '24px', '8px'] }}
                 transition={{ repeat: Infinity, duration: 0.6, delay: i * 0.15, ease: 'easeInOut' }}
                 style={{ width: '4px', background: 'var(--text-h)', borderRadius: '2px' }}
               />
             ))}
           </div>
           
           <span style={{ color: 'var(--text-h)', fontWeight: 600, fontSize: '0.95rem', minWidth: '150px' }}>
             {displayedText || 'Ecoute en cours...'}
           </span>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
