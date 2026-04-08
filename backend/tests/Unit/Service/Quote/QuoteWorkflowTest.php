<?php

namespace App\Tests\Unit\Service\Quote;

use App\Entity\Quote;
use App\Service\Quote\QuoteStateMachine;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

class QuoteWorkflowTest extends TestCase
{
    /**
     * Verifie que la transition send est possible depuis DRAFT.
     */
    public function testCanSendFromDraft(): void
    {
        $quote = new Quote();
        $workflow = $this->createWorkflowMock(true);
        $stateMachine = new QuoteStateMachine($workflow);

        $this->assertTrue($stateMachine->can($quote, 'send'));
    }

    /**
     * Verifie que la transition convert est possible depuis ACCEPTED.
     */
    public function testCanConvertFromAccepted(): void
    {
        $quote = new Quote();
        $workflow = $this->createWorkflowMock(true);
        $stateMachine = new QuoteStateMachine($workflow);

        $this->assertTrue($stateMachine->can($quote, 'convert'));
    }

    /**
     * Verifie qu'une transition invalide leve une exception.
     */
    public function testApplyInvalidTransitionThrowsException(): void
    {
        $quote = new Quote();
        $workflow = $this->createWorkflowMock(false);
        $stateMachine = new QuoteStateMachine($workflow);

        $this->expectException(\LogicException::class);
        $stateMachine->apply($quote, 'convert');
    }

    /**
     * Verifie que les transitions actives sont correctement retournees.
     */
    public function testGetEnabledTransitions(): void
    {
        $quote = new Quote();

        $transition1 = new Transition('send', 'DRAFT', 'SENT');
        $transition2 = new Transition('accept', 'SENT', 'ACCEPTED');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$transition1, $transition2]);

        $stateMachine = new QuoteStateMachine($workflow);
        $transitions = $stateMachine->getEnabledTransitions($quote);

        $this->assertSame(['send', 'accept'], $transitions);
    }

    /**
     * Verifie que la transition send est appliquee correctement.
     */
    public function testApplySendTransition(): void
    {
        $quote = new Quote();

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('can')->willReturn(true);
        $workflow->expects($this->once())
            ->method('apply')
            ->with($quote, 'send')
            ->willReturn(new Marking(['SENT' => 1]));

        $stateMachine = new QuoteStateMachine($workflow);
        $stateMachine->apply($quote, 'send');
    }

    private function createWorkflowMock(bool $canResult): WorkflowInterface
    {
        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('can')->willReturn($canResult);

        if ($canResult) {
            $workflow->method('apply')->willReturn(new Marking());
        }

        return $workflow;
    }
}
