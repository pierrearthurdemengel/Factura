<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Quote;
use App\Entity\QuoteEvent;
use App\Service\Quote\QuoteNumberGenerator;
use App\Service\Quote\QuoteStateMachine;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Traite l'envoi d'un devis : genere le numero, recalcule les totaux,
 * applique la transition DRAFT → SENT et enregistre l'evenement d'audit.
 *
 * @implements ProcessorInterface<Quote, Quote>
 */
class QuoteSendProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly QuoteStateMachine $stateMachine,
        private readonly QuoteNumberGenerator $numberGenerator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Quote $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Quote
    {
        $quote = $data;

        // Generer le numero de devis s'il n'en a pas encore
        if (null === $quote->getNumber()) {
            $number = $this->numberGenerator->generate($quote->getSeller());
            $quote->setNumber($number);
        }

        // Recalculer les montants de chaque ligne et les totaux
        foreach ($quote->getLines() as $line) {
            $line->computeAmounts();
        }
        $quote->computeTotals();

        // Appliquer la transition DRAFT → SENT
        $this->stateMachine->apply($quote, 'send');

        // Enregistrer l'evenement d'audit
        $event = new QuoteEvent($quote, 'STATUS_CHANGED', [
            'from' => 'DRAFT',
            'to' => 'SENT',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
        $quote->addEvent($event);
        $this->em->persist($event);

        $this->em->flush();

        return $quote;
    }
}
