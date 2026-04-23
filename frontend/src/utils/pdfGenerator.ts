import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';

/**
 * Capture a specific DOM element (identified by a selector) 
 * and generate a high-definition PDF using jsPDF.
 */
export const downloadLocalPdf = async (selector: string, filename: string = 'facture.pdf') => {
  const element = document.querySelector(selector) as HTMLElement;
  if (!element) throw new Error("Element not found for PDF generation");

  try {
    // Add an animation class before rendering for UI feedback
    element.classList.add('pdf-rendering-anim');
    
    // We scale by 2 for higher resolution
    const canvas = await html2canvas(element, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
    
    // A4 dimensions in mm
    const pdf = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: 'a4'
    });
    
    const imgData = canvas.toDataURL('image/jpeg', 1);
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
    
    pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
    pdf.save(filename);
    
  } finally {
    element.classList.remove('pdf-rendering-anim');
  }
};
