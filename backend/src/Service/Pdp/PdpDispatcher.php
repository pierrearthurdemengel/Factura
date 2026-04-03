<?php

namespace App\Service\Pdp;

use App\Entity\Company;

/**
 * Selectionne la PDP appropriee selon la configuration de l'entreprise.
 */
class PdpDispatcher
{
    /** @var array<string, PdpClientInterface> */
    private array $clients = [];

    public function __construct(
        private readonly NullPdpClient $nullClient,
    ) {
    }

    public function registerClient(PdpClientInterface $client): void
    {
        $this->clients[$client->getName()] = $client;
    }

    /**
     * Retourne le client PDP configure pour l'entreprise.
     * Si aucune PDP n'est configuree, retourne le client null (sandbox).
     */
    public function getClientForCompany(Company $company): PdpClientInterface
    {
        $pdpName = $company->getDefaultPdp();

        if (null !== $pdpName && isset($this->clients[$pdpName])) {
            return $this->clients[$pdpName];
        }

        return $this->nullClient;
    }
}
