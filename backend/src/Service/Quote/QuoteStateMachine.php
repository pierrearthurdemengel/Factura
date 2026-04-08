<?php

namespace App\Service\Quote;

use App\Entity\Quote;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Wrapper autour du workflow Symfony pour le cycle de vie des devis.
 */
class QuoteStateMachine
{
    public function __construct(
        private readonly WorkflowInterface $quoteStateMachine,
    ) {
    }

    /**
     * Applique une transition sur le devis.
     *
     * @throws \LogicException Si la transition n'est pas valide depuis l'etat courant
     */
    public function apply(Quote $quote, string $transition): void
    {
        if (!$this->quoteStateMachine->can($quote, $transition)) {
            throw new \LogicException(sprintf('La transition "%s" n\'est pas possible depuis l\'etat "%s".', $transition, $quote->getStatus()));
        }

        $this->quoteStateMachine->apply($quote, $transition);
    }

    /**
     * Verifie si une transition est possible.
     */
    public function can(Quote $quote, string $transition): bool
    {
        return $this->quoteStateMachine->can($quote, $transition);
    }

    /**
     * Retourne la liste des transitions possibles depuis l'etat courant.
     *
     * @return string[]
     */
    public function getEnabledTransitions(Quote $quote): array
    {
        $transitions = $this->quoteStateMachine->getEnabledTransitions($quote);

        return array_map(fn ($t) => $t->getName(), $transitions);
    }
}
