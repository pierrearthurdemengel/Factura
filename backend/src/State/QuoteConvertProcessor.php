<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invoice;
use App\Entity\Quote;
use App\Entity\QuoteEvent;
use App\Service\Quote\QuoteToInvoiceConverter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Traite la conversion d'un devis accepte en facture brouillon.
 * Le devis passe en statut CONVERTED et une nouvelle Invoice DRAFT est creee.
 *
 * @implements ProcessorInterface<Quote, Invoice>
 */
class QuoteConvertProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly QuoteToInvoiceConverter $converter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Quote $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        $quote = $data;

        $invoice = $this->converter->convert($quote);

        // Enregistrer l'evenement d'audit sur le devis
        $event = new QuoteEvent($quote, 'CONVERTED', [
            'invoice_id' => $invoice->getId()?->toRfc4122(),
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
        $quote->addEvent($event);
        $this->em->persist($event);
        $this->em->flush();

        return $invoice;
    }
}
