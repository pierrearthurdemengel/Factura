<?php

namespace App\Service\Factoring;

use App\Entity\FactoringEvent;
use App\Entity\FactoringRequest;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Gere le cycle de vie complet des demandes d'affacturage.
 *
 * Coordonne la verification d'eligibilite, la creation de la demande,
 * et le traitement des webhooks partenaire.
 */
class FactoringRequestService
{
    private const ALLOWED_PARTNERS = ['defacto', 'silvr', 'aria', 'hokodo'];

    public function __construct(
        private readonly FactoringEligibilityChecker $eligibilityChecker,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Cree une demande d'affacturage pour une facture.
     *
     * @throws \InvalidArgumentException Si la facture n'est pas eligible ou le partenaire invalide
     */
    public function requestFinancing(Invoice $invoice, string $partnerId): FactoringRequest
    {
        // Verification du partenaire
        if (!in_array($partnerId, self::ALLOWED_PARTNERS, true)) {
            throw new \InvalidArgumentException(sprintf('Partenaire invalide : %s. Partenaires autorises : %s.', $partnerId, implode(', ', self::ALLOWED_PARTNERS)));
        }

        // Verification d'eligibilite
        $eligibility = $this->eligibilityChecker->check($invoice);
        if (!$eligibility['eligible']) {
            throw new \InvalidArgumentException($eligibility['reason'] ?? 'La facture n\'est pas eligible a l\'affacturage.');
        }

        // Creer la demande
        $request = new FactoringRequest();
        $request->setInvoice($invoice);
        $request->setCompany($invoice->getSeller());
        $request->setPartnerId($partnerId);
        $request->setAmount($eligibility['proposedAmount'] ?? 0);
        $request->setFee($eligibility['estimatedFee'] ?? 0);
        $request->setCommission($eligibility['commission'] ?? 0);
        $request->setClientScore($eligibility['clientScore']);

        $this->em->persist($request);

        // Enregistrer l'evenement
        $event = new FactoringEvent($request, 'REQUESTED', [
            'partnerId' => $partnerId,
            'amount' => $request->getAmount(),
            'fee' => $request->getFee(),
            'commission' => $request->getCommission(),
            'clientScore' => $request->getClientScore(),
        ]);
        $this->em->persist($event);
        $this->em->flush();

        $this->logger->info('Demande d\'affacturage creee.', [
            'requestId' => $request->getId()?->toRfc4122(),
            'invoiceNumber' => $invoice->getNumber(),
            'partnerId' => $partnerId,
            'amount' => $request->getAmount(),
        ]);

        return $request;
    }

    /**
     * Traite un webhook provenant d'un partenaire d'affacturage.
     *
     * @param array<string, mixed> $payload
     */
    public function handleWebhook(string $partnerId, array $payload): void
    {
        $referenceId = $payload['referenceId'] ?? null;
        $eventType = $payload['event'] ?? null;

        if (null === $referenceId || null === $eventType) {
            $this->logger->warning('Webhook affacturage invalide : referenceId ou event manquant.', [
                'partnerId' => $partnerId,
                'payload' => $payload,
            ]);

            return;
        }

        /** @var FactoringRequest|null $request */
        $request = $this->em->getRepository(FactoringRequest::class)->findOneBy([
            'partnerReferenceId' => $referenceId,
            'partnerId' => $partnerId,
        ]);

        if (null === $request) {
            $this->logger->warning('Webhook affacturage : demande introuvable.', [
                'partnerId' => $partnerId,
                'referenceId' => $referenceId,
            ]);

            return;
        }

        // Enregistrer l'evenement webhook brut
        $webhookEvent = new FactoringEvent($request, 'WEBHOOK_RECEIVED', $payload);
        $this->em->persist($webhookEvent);

        // Traiter selon le type d'evenement
        match ($eventType) {
            'factoring_approved' => $this->handleApproval($request, $payload),
            'factoring_rejected' => $this->handleRejection($request, $payload),
            'funds_transferred' => $this->handlePayment($request),
            default => $this->logger->warning('Type d\'evenement webhook inconnu.', [
                'eventType' => $eventType,
                'partnerId' => $partnerId,
            ]),
        };

        $this->em->flush();
    }

    /**
     * Annule une demande d'affacturage en attente.
     *
     * @throws \LogicException Si la demande ne peut pas etre annulee
     */
    public function cancelRequest(FactoringRequest $request): void
    {
        if (!$request->isCancellable()) {
            throw new \LogicException(sprintf('La demande en statut %s ne peut pas etre annulee.', $request->getStatus()));
        }

        $request->setStatus(FactoringRequest::STATUS_CANCELLED);

        $event = new FactoringEvent($request, 'CANCELLED');
        $this->em->persist($event);
        $this->em->flush();

        $this->logger->info('Demande d\'affacturage annulee.', [
            'requestId' => $request->getId()?->toRfc4122(),
        ]);
    }

    /**
     * Retourne la liste des partenaires autorises.
     *
     * @return string[]
     */
    public static function getAllowedPartners(): array
    {
        return self::ALLOWED_PARTNERS;
    }

    /**
     * Traite l'approbation d'une demande par le partenaire.
     *
     * @param array<string, mixed> $payload
     */
    private function handleApproval(FactoringRequest $request, array $payload): void
    {
        $request->setStatus(FactoringRequest::STATUS_APPROVED);
        $request->setApprovedAt(new \DateTimeImmutable());

        if (isset($payload['partnerReferenceId'])) {
            $request->setPartnerReferenceId((string) $payload['partnerReferenceId']);
        }

        $event = new FactoringEvent($request, 'APPROVED', [
            'approvedAt' => (new \DateTimeImmutable())->format('c'),
        ]);
        $this->em->persist($event);

        $this->logger->info('Demande d\'affacturage approuvee.', [
            'requestId' => $request->getId()?->toRfc4122(),
        ]);
    }

    /**
     * Traite le rejet d'une demande par le partenaire.
     *
     * @param array<string, mixed> $payload
     */
    private function handleRejection(FactoringRequest $request, array $payload): void
    {
        $request->setStatus(FactoringRequest::STATUS_REJECTED);
        $request->setRejectionReason($payload['reason'] ?? 'Raison non communiquee');

        $event = new FactoringEvent($request, 'REJECTED', [
            'reason' => $request->getRejectionReason(),
        ]);
        $this->em->persist($event);

        $this->logger->info('Demande d\'affacturage rejetee.', [
            'requestId' => $request->getId()?->toRfc4122(),
            'reason' => $request->getRejectionReason(),
        ]);
    }

    /**
     * Traite la confirmation de versement des fonds.
     */
    private function handlePayment(FactoringRequest $request): void
    {
        $request->setStatus(FactoringRequest::STATUS_PAID);
        $request->setPaidAt(new \DateTimeImmutable());

        $event = new FactoringEvent($request, 'PAID', [
            'paidAt' => (new \DateTimeImmutable())->format('c'),
        ]);
        $this->em->persist($event);

        $this->logger->info('Fonds d\'affacturage verses.', [
            'requestId' => $request->getId()?->toRfc4122(),
            'amount' => $request->getAmount(),
        ]);
    }
}
