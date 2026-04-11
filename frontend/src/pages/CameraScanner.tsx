import { useState, useRef, useEffect, useCallback } from 'react';
import api from '../api/factura';
import './AppLayout.css';

// Resultat OCR retourne par l'API apres traitement du justificatif
interface OcrResult {
  amount: string | null;
  date: string | null;
  vendor: string | null;
}

// Etats possibles du scanner
type ScannerStatus = 'idle' | 'capturing' | 'preview' | 'uploading' | 'result';

export default function CameraScanner() {
  const [status, setStatus] = useState<ScannerStatus>('idle');
  const [capturedImage, setCapturedImage] = useState<string | null>(null);
  const [capturedBlob, setCapturedBlob] = useState<Blob | null>(null);
  const [ocrResult, setOcrResult] = useState<OcrResult | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [cameraAvailable, setCameraAvailable] = useState(true);

  const videoRef = useRef<HTMLVideoElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Arrete le flux camera proprement
  const stopStream = useCallback(() => {
    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop());
      streamRef.current = null;
    }
  }, []);

  // Nettoyage du flux au demontage du composant
  useEffect(() => {
    return () => {
      stopStream();
    };
  }, [stopStream]);

  // Demarre le flux camera avec la camera arriere en priorite
  const startCamera = async () => {
    setErrorMessage(null);
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
      });
      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
      }
      setStatus('capturing');
      setCameraAvailable(true);
    } catch (err: unknown) {
      // Permission refusee ou camera indisponible
      const message = err instanceof Error ? err.message : String(err);
      if (message.includes('NotAllowed') || message.includes('Permission')) {
        setErrorMessage(
          "L'acces a la camera a ete refuse. Veuillez autoriser l'acces dans les parametres de votre navigateur."
        );
      } else {
        setErrorMessage(
          'Camera indisponible. Utilisez le bouton ci-dessous pour selectionner un fichier.'
        );
      }
      setCameraAvailable(false);
    }
  };

  // Capture une image depuis le flux video en cours
  const capturePhoto = () => {
    const video = videoRef.current;
    const canvas = canvasRef.current;
    if (!video || !canvas) return;

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Genere un apercu en base64 et un blob pour l'envoi
    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
    setCapturedImage(dataUrl);

    canvas.toBlob(
      (blob) => {
        if (blob) {
          setCapturedBlob(blob);
        }
      },
      'image/jpeg',
      0.85
    );

    // Arrete la camera apres la capture pour economiser la batterie
    stopStream();
    setStatus('preview');
  };

  // Reprend une nouvelle photo en relancant la camera
  const retake = () => {
    setCapturedImage(null);
    setCapturedBlob(null);
    setOcrResult(null);
    setErrorMessage(null);
    startCamera();
  };

  // Envoie l'image capturee ou selectionnee au serveur pour OCR
  const uploadImage = async (blob: Blob) => {
    setStatus('uploading');
    setErrorMessage(null);

    const formData = new FormData();
    formData.append('file', blob, 'receipt.jpg');

    try {
      const response = await api.post<OcrResult>('/receipts/scan', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setOcrResult(response.data);
      setStatus('result');
    } catch {
      setErrorMessage("Erreur lors de l'envoi. Veuillez reessayer.");
      setStatus('preview');
    }
  };

  // Gere la selection d'un fichier via l'input natif (desktop ou fallback mobile)
  const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      const result = e.target?.result;
      if (typeof result === 'string') {
        setCapturedImage(result);
      }
    };
    reader.readAsDataURL(file);

    setCapturedBlob(file);
    stopStream();
    setStatus('preview');
  };

  // Reinitialise le scanner pour une nouvelle capture
  const reset = () => {
    setCapturedImage(null);
    setCapturedBlob(null);
    setOcrResult(null);
    setErrorMessage(null);
    setStatus('idle');
    stopStream();
  };

  return (
    <div className="app-container">
      <h1 className="app-page-title">Scanner un justificatif</h1>

      {/* Message d'erreur global */}
      {errorMessage && (
        <div className="app-alert app-alert--error">
          {errorMessage}
        </div>
      )}

      {/* Etat initial : proposer de lancer la camera ou de selectionner un fichier */}
      {status === 'idle' && (
        <div className="app-empty">
          <div
            className="app-card"
            style={{
              maxWidth: 400,
              margin: '0 auto',
              padding: '2rem 1.5rem',
              textAlign: 'center',
            }}
          >
            <div className="app-empty-icon">
              <svg
                viewBox="0 0 24 24"
                width="48"
                height="48"
                fill="none"
                stroke="var(--accent)"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                <circle cx="12" cy="13" r="4" />
              </svg>
            </div>
            <p className="app-empty-title">
              Photographiez votre justificatif
            </p>
            <p className="app-desc" style={{ marginBottom: '1.5rem' }}>
              Ticket de caisse, facture fournisseur, note de frais... Le montant,
              la date et le fournisseur seront extraits automatiquement.
            </p>

            <div className="app-pills" style={{ flexDirection: 'column', gap: '0.75rem' }}>
              <button
                className="app-btn-primary"
                onClick={startCamera}
                style={{ width: '100%' }}
              >
                Ouvrir la camera
              </button>

              {/* Fallback pour les appareils sans camera ou les navigateurs desktop */}
              <button
                className="app-btn-outline"
                onClick={() => fileInputRef.current?.click()}
                style={{ width: '100%' }}
              >
                Choisir un fichier
              </button>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                capture="environment"
                className="app-hidden"
                onChange={handleFileSelect}
              />
            </div>
          </div>
        </div>
      )}

      {/* Etat capture : flux video en direct avec bouton de capture */}
      {status === 'capturing' && (
        <div
          style={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            gap: '1.5rem',
          }}
        >
          <div
            style={{
              position: 'relative',
              width: '100%',
              maxWidth: 500,
              borderRadius: '16px',
              overflow: 'hidden',
              background: '#000',
            }}
          >
            <video
              ref={videoRef}
              autoPlay
              playsInline
              muted
              style={{
                width: '100%',
                display: 'block',
                borderRadius: '16px',
              }}
            />
            {/* Cadre indicatif pour centrer le document */}
            <div
              style={{
                position: 'absolute',
                top: '10%',
                left: '10%',
                right: '10%',
                bottom: '10%',
                border: '2px dashed rgba(255, 255, 255, 0.4)',
                borderRadius: '12px',
                pointerEvents: 'none',
              }}
            />
          </div>

          {/* Bouton de capture circulaire */}
          <div className="app-actions-row" style={{ justifyContent: 'center', marginTop: 0 }}>
            <button
              onClick={() => {
                stopStream();
                reset();
              }}
              style={{
                width: 48,
                height: 48,
                borderRadius: '50%',
                border: '2px solid var(--border)',
                background: 'var(--surface)',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: 'var(--text)',
              }}
              title="Annuler"
            >
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>

            <button
              onClick={capturePhoto}
              style={{
                width: 72,
                height: 72,
                borderRadius: '50%',
                border: '4px solid var(--accent)',
                background: 'transparent',
                cursor: 'pointer',
                padding: 4,
              }}
              title="Prendre la photo"
            >
              <div
                style={{
                  width: '100%',
                  height: '100%',
                  borderRadius: '50%',
                  background: 'var(--accent)',
                  transition: 'transform 0.15s',
                }}
              />
            </button>

            {/* Bouton pour utiliser un fichier a la place */}
            <button
              onClick={() => fileInputRef.current?.click()}
              style={{
                width: 48,
                height: 48,
                borderRadius: '50%',
                border: '2px solid var(--border)',
                background: 'var(--surface)',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: 'var(--text)',
              }}
              title="Choisir un fichier"
            >
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
            </button>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              className="app-hidden"
              onChange={handleFileSelect}
            />
          </div>
        </div>
      )}

      {/* Canvas cache utilise pour la capture d'image */}
      <canvas ref={canvasRef} className="app-hidden" />

      {/* Etat apercu : image capturee avec actions reprendre / envoyer */}
      {status === 'preview' && capturedImage && (
        <div
          style={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            gap: '1.5rem',
          }}
        >
          <div
            style={{
              width: '100%',
              maxWidth: 500,
              borderRadius: '16px',
              overflow: 'hidden',
              background: '#000',
            }}
          >
            <img
              src={capturedImage}
              alt="Apercu du justificatif"
              style={{
                width: '100%',
                display: 'block',
                borderRadius: '16px',
              }}
            />
          </div>

          <div className="app-actions-row" style={{ maxWidth: 500, width: '100%', marginTop: 0 }}>
            <button
              className="app-btn-outline-danger"
              onClick={retake}
              style={{ flex: 1 }}
            >
              Reprendre
            </button>
            <button
              className="app-btn-primary"
              onClick={() => {
                if (capturedBlob) {
                  uploadImage(capturedBlob);
                }
              }}
              style={{ flex: 1 }}
            >
              Envoyer
            </button>
          </div>
        </div>
      )}

      {/* Etat envoi en cours */}
      {status === 'uploading' && (
        <div className="app-empty">
          <div
            style={{
              width: 48,
              height: 48,
              border: '4px solid var(--border)',
              borderTopColor: 'var(--accent)',
              borderRadius: '50%',
              margin: '0 auto 1.5rem',
              animation: 'spin 0.8s linear infinite',
            }}
          />
          <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
          <p className="app-empty-title">
            Analyse en cours...
          </p>
          <p className="app-empty-desc">
            Extraction des informations du justificatif
          </p>
        </div>
      )}

      {/* Etat resultat : donnees extraites par OCR */}
      {status === 'result' && ocrResult && (
        <div
          style={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            gap: '1.5rem',
          }}
        >
          {/* Miniature de l'image analysee */}
          {capturedImage && (
            <div
              style={{
                width: '100%',
                maxWidth: 300,
                borderRadius: '12px',
                overflow: 'hidden',
                opacity: 0.7,
              }}
            >
              <img
                src={capturedImage}
                alt="Justificatif analyse"
                style={{ width: '100%', display: 'block', borderRadius: '12px' }}
              />
            </div>
          )}

          {/* Carte de resultats OCR */}
          <div
            className="app-card"
            style={{
              width: '100%',
              maxWidth: 500,
            }}
          >
            <h2 className="app-section-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginTop: 0 }}>
              <svg
                viewBox="0 0 24 24"
                width="20"
                height="20"
                fill="none"
                stroke="#22c55e"
                strokeWidth="2"
              >
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
              </svg>
              Informations extraites
            </h2>

            <div className="app-list">
              {/* Montant */}
              <div className="app-list-item">
                <span className="app-list-item-sub">
                  Montant
                </span>
                <span className="app-list-item-value" style={{ color: ocrResult.amount ? 'var(--text-h)' : 'var(--text)' }}>
                  {ocrResult.amount
                    ? `${ocrResult.amount} EUR`
                    : 'Non detecte'}
                </span>
              </div>

              {/* Date */}
              <div className="app-list-item">
                <span className="app-list-item-sub">
                  Date
                </span>
                <span className="app-list-item-value" style={{ color: ocrResult.date ? 'var(--text-h)' : 'var(--text)' }}>
                  {ocrResult.date
                    ? new Date(ocrResult.date).toLocaleDateString('fr-FR')
                    : 'Non detectee'}
                </span>
              </div>

              {/* Fournisseur */}
              <div className="app-list-item">
                <span className="app-list-item-sub">
                  Fournisseur
                </span>
                <span className="app-list-item-value" style={{ color: ocrResult.vendor ? 'var(--text-h)' : 'var(--text)' }}>
                  {ocrResult.vendor || 'Non detecte'}
                </span>
              </div>
            </div>
          </div>

          {/* Actions apres resultat */}
          <div className="app-actions-row" style={{ maxWidth: 500, width: '100%', marginTop: 0 }}>
            <button
              className="app-btn-primary"
              onClick={reset}
              style={{ flex: 1 }}
            >
              Nouveau scan
            </button>
          </div>
        </div>
      )}

      {/* Fallback : input fichier affiche quand la camera n'est pas disponible */}
      {!cameraAvailable && status === 'idle' && (
        <div
          className="app-card"
          style={{
            textAlign: 'center',
            maxWidth: 400,
            margin: '1rem auto 0',
          }}
        >
          <p className="app-desc" style={{ marginBottom: '1rem' }}>
            Selectionnez une photo de votre justificatif depuis votre appareil.
          </p>
          <button
            className="app-btn-primary"
            onClick={() => fileInputRef.current?.click()}
            style={{ width: '100%' }}
          >
            Parcourir les fichiers
          </button>
          <input
            ref={fileInputRef}
            type="file"
            accept="image/*"
            className="app-hidden"
            onChange={handleFileSelect}
          />
        </div>
      )}
    </div>
  );
}
