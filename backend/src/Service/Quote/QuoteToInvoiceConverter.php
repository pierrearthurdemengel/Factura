<?php

namespace App\Service\Quote;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Quote;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Convertit un devis accepte en facture brouillon.
 *
 * Copie toutes les donnees du devis (vendeur, acheteur, lignes, totaux)
 * vers une nouvelle Invoice en statut DRAFT. Le devis est ensuite
 * passe en statut CONVERTED via la state machine.
 */
class QuoteToInvoiceConverter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QuoteStateMachine $stateMachine,
    ) {
    }

    /**
     * Convertit le devis en facture et retourne la facture creee.
     *
     * @throws \LogicException Si le devis n'est pas en statut ACCEPTED
     */
    public function convert(Quote $quote): Invoice
    {
        if ('ACCEPTED' !== $quote->getStatus()) {
            throw new \LogicException('Seul un devis accepte peut etre converti en facture.');
        }

        $invoice = new Invoice();
        $invoice->setSeller($quote->getSeller());
        $invoice->setBuyer($quote->getBuyer());
        $invoice->setIssueDate(new \DateTimeImmutable());
        $invoice->setCurrency($quote->getCurrency());
        $invoice->setLegalMention($quote->getLegalMention());
        $invoice->setSourceQuote($quote);

        // Copie des lignes du devis vers la facture
        $position = 1;
        foreach ($quote->getLines() as $quoteLine) {
            $invoiceLine = new InvoiceLine();
            $invoiceLine->setPosition($position);
            $invoiceLine->setDescription($quoteLine->getDescription());
            $invoiceLine->setQuantity($quoteLine->getQuantity());
            $invoiceLine->setUnit($quoteLine->getUnit());
            $invoiceLine->setUnitPriceExcludingTax($quoteLine->getUnitPriceExcludingTax());
            $invoiceLine->setVatRate($quoteLine->getVatRate());
            $invoiceLine->computeAmounts();

            $invoice->addLine($invoiceLine);
            ++$position;
        }

        $invoice->computeTotals();

        // Transition du devis : ACCEPTED → CONVERTED
        $this->stateMachine->apply($quote, 'convert');
        $quote->setConvertedInvoice($invoice);

        $this->em->persist($invoice);
        $this->em->flush();

        return $invoice;
    }
}
