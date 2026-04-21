<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use App\Service\Format\FacturXGenerator;
use App\Service\Format\InvoicePdfGenerator;
use App\Service\Format\UblGenerator;
use Doctrine\ORM\EntityManagerInterface;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints de telechargement des factures au format XML (CII / UBL).
 */
class InvoiceExportController extends AbstractController
{
    /**
     * Telecharge la facture au format Factur-X PDF/A-3 (PDF avec XML CII D16B embarque).
     */
    #[Route('/api/invoices/{id}/pdf', name: 'api_invoice_download_pdf', methods: ['GET'])]
    public function downloadPdf(
        string $id,
        EntityManagerInterface $em,
        FacturXGenerator $facturXGenerator,
        InvoicePdfGenerator $pdfGenerator,
    ): Response {
        $invoice = $this->findInvoiceForUser($id, $em);

        if (null === $invoice) {
            return new Response('Facture introuvable.', Response::HTTP_NOT_FOUND);
        }

        // Generer le document CII D16B via le builder
        $docBuilder = $facturXGenerator->buildDocument($invoice);

        // Generer le PDF de mise en page
        $pdfContent = $pdfGenerator->generate($invoice);

        // Fusionner XML + PDF pour creer un Factur-X PDF/A-3
        $pdfBuilder = ZugferdDocumentPdfBuilder::fromPdfString($docBuilder, $pdfContent);
        $pdfBuilder->setAdditionalCreatorTool('Ma Facture Pro v1.0');
        $pdfBuilder->generateDocument();
        $mergedPdf = $pdfBuilder->downloadString();

        $filename = sprintf('%s.pdf', $invoice->getNumber() ?? 'brouillon');

        return new Response($mergedPdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /**
     * Telecharge la facture au format Factur-X XML (CII D16B).
     */
    #[Route('/api/invoices/{id}/download/facturx', name: 'api_invoice_download_facturx', methods: ['GET'])]
    public function downloadFacturX(
        string $id,
        EntityManagerInterface $em,
        FacturXGenerator $generator,
    ): Response {
        $invoice = $this->findInvoiceForUser($id, $em);

        if (null === $invoice) {
            return new Response('Facture introuvable.', Response::HTTP_NOT_FOUND);
        }

        $xml = $generator->generate($invoice);
        $filename = sprintf('%s-facturx.xml', $invoice->getNumber() ?? 'brouillon');

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /**
     * Telecharge la facture au format UBL 2.1.
     */
    #[Route('/api/invoices/{id}/download/ubl', name: 'api_invoice_download_ubl', methods: ['GET'])]
    public function downloadUbl(
        string $id,
        EntityManagerInterface $em,
        UblGenerator $generator,
    ): Response {
        $invoice = $this->findInvoiceForUser($id, $em);

        if (null === $invoice) {
            return new Response('Facture introuvable.', Response::HTTP_NOT_FOUND);
        }

        $xml = $generator->generate($invoice);
        $filename = sprintf('%s-ubl.xml', $invoice->getNumber() ?? 'brouillon');

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /**
     * Recherche une facture appartenant a l'utilisateur connecte.
     */
    private function findInvoiceForUser(string $id, EntityManagerInterface $em): ?Invoice
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (null === $company) {
            return null;
        }

        $invoice = $em->getRepository(Invoice::class)->find($id);

        if (null === $invoice) {
            return null;
        }

        // Verifier que la facture appartient a l'entreprise de l'utilisateur
        $seller = $invoice->getSeller();
        if (null === $seller || $seller->getId()?->toRfc4122() !== $company->getId()?->toRfc4122()) {
            return null;
        }

        return $invoice;
    }
}
