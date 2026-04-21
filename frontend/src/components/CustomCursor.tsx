import { useEffect, useState } from 'react';
import { motion, useMotionValue, useSpring } from 'framer-motion';

export default function CustomCursor() {
  const [isHovering, setIsHovering] = useState(false);
  const cursorX = useMotionValue(-100);
  const cursorY = useMotionValue(-100);
  
  const springX = useSpring(cursorX, { stiffness: 800, damping: 35 });
  const springY = useSpring(cursorY, { stiffness: 800, damping: 35 });

  useEffect(() => {
    const moveCursor = (e: PointerEvent) => {
      cursorX.set(e.clientX - 10);
      cursorY.set(e.clientY - 10);
      
      const target = e.target as HTMLElement;
      // Detect interactive elements to trigger magnetic inflation
      if (target.closest('button, a, input, select, textarea, .app-card, [data-magnetic], .widget-drag-handle')) {
        setIsHovering(true);
      } else {
        setIsHovering(false);
      }
    };

    globalThis.addEventListener('pointermove', moveCursor as EventListener);
    return () => {
       globalThis.removeEventListener('pointermove', moveCursor as EventListener);
    };
  }, [cursorX, cursorY]);

  return (
    <>
      <style>{`
        body, * { cursor: none !important; }
      `}</style>
      
      {/* Outer Springy Ring */}
      <motion.div
        style={{
          position: 'fixed',
          top: 0, left: 0,
          width: 20, height: 20,
          borderRadius: '50%',
          border: '1px solid var(--text-h)',
          pointerEvents: 'none',
          x: springX,
          y: springY,
          zIndex: 99999,
          mixBlendMode: 'difference'
        }}
        animate={{
          scale: isHovering ? 3 : 1,
          opacity: isHovering ? 0.3 : 1,
          borderWidth: isHovering ? '0.5px' : '2px'
        }}
        transition={{ type: 'spring', stiffness: 300, damping: 25 }}
      />
      
      {/* Inner Dot */}
      <motion.div
        style={{
          position: 'fixed',
          top: 0, left: 0,
          width: 6, height: 6,
          borderRadius: '50%',
          backgroundColor: 'var(--text-h)',
          pointerEvents: 'none',
          x: cursorX,
          y: cursorY,
          marginLeft: 7, 
          marginTop: 7,
          zIndex: 99999,
          mixBlendMode: 'difference'
        }}
        animate={{
          opacity: isHovering ? 0 : 1,
          scale: isHovering ? 0 : 1
        }}
      />
    </>
  );
}
