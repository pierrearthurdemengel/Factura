<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Wrapper autour du Workflow Symfony pour la gestion du cycle de vie des factures.
 * Centralise les transitions et les verifications de garde.
 */
class InvoiceStateMachine
{
    public function __construct(
        private readonly WorkflowInterface $invoiceStateMachine,
    ) {
    }

    /**
     * Applique une transition sur la facture.
     *
     * @throws \LogicException Si la transition n'est pas possible
     */
    public function apply(Invoice $invoice, string $transition): void
    {
        $this->invoiceStateMachine->apply($invoice, $transition);
    }

    /**
     * Verifie si une transition est possible.
     */
    public function can(Invoice $invoice, string $transition): bool
    {
        return $this->invoiceStateMachine->can($invoice, $transition);
    }

    /**
     * Retourne la liste des transitions possibles.
     *
     * @return string[]
     */
    public function getEnabledTransitions(Invoice $invoice): array
    {
        $transitions = $this->invoiceStateMachine->getEnabledTransitions($invoice);

        return array_map(fn ($t) => $t->getName(), $transitions);
    }
}
