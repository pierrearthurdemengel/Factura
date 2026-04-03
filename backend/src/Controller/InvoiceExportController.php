<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use App\Service\Format\FacturXGenerator;
use App\Service\Format\UblGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints de telechargement des factures au format XML (CII / UBL).
 */
class InvoiceExportController extends AbstractController
{
    /**
     * Telecharge la facture au format Factur-X (CII D16B).
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
        if ($invoice->getSeller()->getId()?->toRfc4122() !== $company->getId()?->toRfc4122()) {
            return null;
        }

        return $invoice;
    }
}
