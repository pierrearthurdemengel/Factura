<?php

namespace App\Service\PaymentNetwork;

use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceShareLink;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gere le partage de factures via des liens publics.
 * Chaque facture emise peut etre partagee via un lien unique
 * qui permet au destinataire de visualiser et confirmer la reception.
 */
class InvoiceShareService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Cree un lien de partage pour une facture emise.
     * Un seul lien actif par facture (reutilise si existant et non expire).
     */
    public function createShareLink(Invoice $invoice, ?string $referralCode = null): InvoiceShareLink
    {
        // Verifier s'il existe deja un lien actif
        $existing = $this->em->getRepository(InvoiceShareLink::class)->findOneBy([
            'invoice' => $invoice,
        ]);

        if (null !== $existing && !$existing->isExpired()) {
            return $existing;
        }

        $link = new InvoiceShareLink();
        $link->setInvoice($invoice);

        if (null !== $referralCode) {
            $link->setReferralCode($referralCode);
        }

        $this->em->persist($link);
        $this->em->flush();

        return $link;
    }

    /**
     * Recupere un lien de partage par son token.
     */
    public function findByToken(string $token): ?InvoiceShareLink
    {
        return $this->em->getRepository(InvoiceShareLink::class)->findOneBy([
            'token' => $token,
        ]);
    }

    /**
     * Enregistre une consultation du lien et retourne les donnees de la facture.
     *
     * @return array{invoice: Invoice, shareLink: InvoiceShareLink}|null
     */
    public function viewInvoice(string $token): ?array
    {
        $link = $this->findByToken($token);

        if (null === $link || $link->isExpired()) {
            return null;
        }

        $link->markViewed();
        $this->em->flush();

        return [
            'invoice' => $link->getInvoice(),
            'shareLink' => $link,
        ];
    }

    /**
     * Le destinataire confirme la reception de la facture en 1 clic.
     */
    public function acknowledgeReceipt(string $token): bool
    {
        $link = $this->findByToken($token);

        if (null === $link || $link->isExpired() || $link->isAcknowledged()) {
            return false;
        }

        $link->acknowledge();
        $this->em->flush();

        return true;
    }

    /**
     * Detecte si le SIREN de l'acheteur correspond a une entreprise sur la plateforme.
     * Si oui, la reconciliation intra-reseau est possible.
     */
    public function detectIntraNetworkBuyer(Invoice $invoice): ?Company
    {
        $buyer = $invoice->getBuyer();
        $buyerSiren = $buyer->getSiren();

        if (null === $buyerSiren || '' === $buyerSiren) {
            return null;
        }

        // Chercher une Company avec le meme SIREN
        $matchingCompany = $this->em->getRepository(Company::class)->findOneBy([
            'siren' => $buyerSiren,
        ]);

        // Verifier que ce n'est pas le vendeur lui-meme
        $sellerId = $invoice->getSeller()?->getId()?->toRfc4122();
        $matchId = $matchingCompany?->getId()?->toRfc4122();

        return (null !== $matchingCompany && $sellerId !== $matchId) ? $matchingCompany : null;
    }
}
