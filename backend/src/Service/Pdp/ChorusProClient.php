<?php

namespace App\Service\Pdp;

use App\Entity\Invoice;
use App\Exception\PdpTransmissionException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client PDP Chorus Pro (API REST).
 * Chorus Pro est la plateforme gratuite de l'Etat, obligatoire pour les factures B2G.
 */
class ChorusProClient implements PdpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $chorusProBaseUrl,
        private readonly string $chorusProLogin,
        private readonly string $chorusProPassword,
    ) {
    }

    public function transmit(Invoice $invoice, string $xmlContent, string $format): string
    {
        try {
            $response = $this->client->request('POST', $this->chorusProBaseUrl . '/factures/deposer', [
                'auth_basic' => [$this->chorusProLogin, $this->chorusProPassword],
                'json' => [
                    'facture' => base64_encode($xmlContent),
                    'formatFichier' => 'ubl' === $format ? 'IN_DP_E1_UBL_INVOICE_2' : 'IN_DP_E1_CII_FACTURX',
                    'nomFichier' => $invoice->getNumber() . '.xml',
                ],
            ]);

            $data = $response->toArray();

            return (string) $data['numeroFluxDepot'];
        } catch (\Throwable $e) {
            throw new PdpTransmissionException($this->getName(), $invoice->getNumber() ?? 'N/A', $e->getMessage());
        }
    }

    public function getStatus(string $pdpReference): PdpStatus
    {
        $response = $this->client->request('GET', $this->chorusProBaseUrl . '/factures/statut/' . $pdpReference, [
            'auth_basic' => [$this->chorusProLogin, $this->chorusProPassword],
        ]);

        $data = $response->toArray();
        $status = $data['statutCourant'] ?? 'INCONNU';

        return match ($status) {
            'EN_COURS_ACHEMINEMENT' => PdpStatus::PENDING,
            'MISE_A_DISPOSITION' => PdpStatus::RECEIVED,
            'ACCEPTEE' => PdpStatus::ACKNOWLEDGED,
            'REFUSEE' => PdpStatus::REJECTED,
            default => PdpStatus::ERROR,
        };
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchIncomingInvoices(\DateTimeImmutable $since): array
    {
        $response = $this->client->request('GET', $this->chorusProBaseUrl . '/factures/entrantes', [
            'auth_basic' => [$this->chorusProLogin, $this->chorusProPassword],
            'query' => [
                'dateDepuis' => $since->format('Y-m-d'),
            ],
        ]);

        return $response->toArray();
    }

    public function getName(): string
    {
        return 'chorus_pro';
    }
}
