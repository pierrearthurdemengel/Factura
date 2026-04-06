import { motion } from 'framer-motion';

export default function AmbientBackground() {
  return (
    <div style={{ position: 'fixed', inset: 0, zIndex: -1, overflow: 'hidden', pointerEvents: 'none', background: 'var(--bg)', transition: 'background 0.4s' }}>
      
      {/* Primary Accent Aurora */}
      <motion.div
        animate={{
          x: ['0%', '-15%', '8%', '-4%', '0%'],
          y: ['0%', '10%', '-15%', '8%', '0%'],
          scale: [1, 1.15, 0.9, 1.1, 1],
          opacity: [0.15, 0.25, 0.1, 0.2, 0.15]
        }}
        transition={{ duration: 22, repeat: Infinity, ease: 'linear' }}
        style={{
          position: 'absolute',
          top: '-15%',
          left: '-10%',
          width: '65vw',
          height: '65vh',
          borderRadius: '50%',
          background: 'radial-gradient(circle, var(--accent) 0%, transparent 70%)',
          filter: 'blur(100px)',
          willChange: 'transform'
        }}
      />

      {/* Secondary Violet Aurora */}
      <motion.div
        animate={{
          x: ['0%', '20%', '-10%', '15%', '0%'],
          y: ['0%', '-15%', '10%', '-5%', '0%'],
          scale: [1, 1.1, 0.85, 1.05, 1],
          opacity: [0.1, 0.2, 0.08, 0.15, 0.1]
        }}
        transition={{ duration: 28, repeat: Infinity, ease: 'linear' }}
        style={{
          position: 'absolute',
          bottom: '-25%',
          right: '-15%',
          width: '75vw',
          height: '75vh',
          borderRadius: '50%',
          background: 'radial-gradient(circle, #8b5cf6 0%, transparent 70%)',
          filter: 'blur(130px)',
          willChange: 'transform'
        }}
      />

      {/* Tertiary Amber Touch */}
      <motion.div
        animate={{
          x: ['0%', '-10%', '10%', '-5%', '0%'],
          y: ['0%', '10%', '-5%', '5%', '0%'],
          scale: [1, 1.2, 0.95, 1.1, 1]
        }}
        transition={{ duration: 35, repeat: Infinity, ease: 'linear' }}
        style={{
          position: 'absolute',
          top: '30%',
          right: '25%',
          width: '40vw',
          height: '40vh',
          borderRadius: '50%',
          background: 'radial-gradient(circle, #f59e0b 0%, transparent 60%)',
          filter: 'blur(120px)',
          opacity: 0.08,
          willChange: 'transform'
        }}
      />
      
      {/* Noise overlay to prevent color banding (simulate WebGL dithering) */}
      <div style={{
        position: 'absolute',
        inset: 0,
        opacity: 0.25,
        backgroundImage: 'url("data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.85%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E")',
        mixBlendMode: 'overlay',
        pointerEvents: 'none'
      }} />

    </div>
  );
}
