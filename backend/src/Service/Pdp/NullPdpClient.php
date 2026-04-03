<?php

namespace App\Service\Pdp;

use App\Entity\Invoice;

/**
 * Client PDP factice pour les tests et le mode sandbox.
 * Simule une transmission reussie sans appel reseau.
 */
class NullPdpClient implements PdpClientInterface
{
    public function transmit(Invoice $invoice, string $xmlContent, string $format): string
    {
        // Retourne une reference fictive
        return 'NULL-' . uniqid();
    }

    public function getStatus(string $pdpReference): PdpStatus
    {
        return PdpStatus::ACKNOWLEDGED;
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchIncomingInvoices(\DateTimeImmutable $since): array
    {
        return [];
    }

    public function getName(): string
    {
        return 'null';
    }
}
