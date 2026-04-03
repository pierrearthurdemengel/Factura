<?php

namespace App\Service\Pdp;

use App\Entity\Invoice;

/**
 * Contrat generique pour les clients PDP (Plateformes de Dematerialisation Partenaires).
 * Chaque PDP (Chorus Pro, etc.) implemente cette interface.
 */
interface PdpClientInterface
{
    /**
     * Transmet une facture a la PDP. Retourne la reference PDP.
     */
    public function transmit(Invoice $invoice, string $xmlContent, string $format): string;

    /**
     * Recupere le statut de traitement d'une facture transmise.
     */
    public function getStatus(string $pdpReference): PdpStatus;

    /**
     * Telecharge les factures entrantes depuis la PDP.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchIncomingInvoices(\DateTimeImmutable $since): array;

    /**
     * Nom de la PDP (pour les logs et la config).
     */
    public function getName(): string;
}
