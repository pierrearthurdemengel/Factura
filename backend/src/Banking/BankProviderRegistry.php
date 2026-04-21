<?php

namespace App\Banking;

use App\Banking\Provider\BankProviderInterface;
use App\Exception\ExternalServiceException;

/**
 * Registre de providers Open Banking.
 *
 * Collecte tous les providers tagges 'app.bank_provider' et les ordonne
 * selon la priorite configuree. Permet de retrouver un provider par nom
 * ou de trouver le premier provider supportant une banque donnee.
 */
class BankProviderRegistry
{
    /** @var array<string, BankProviderInterface> */
    private readonly array $providers;

    /** @var list<string> */
    private readonly array $priority;

    /**
     * @param iterable<BankProviderInterface> $providers
     * @param list<string>                    $providerPriority
     */
    public function __construct(
        iterable $providers,
        array $providerPriority = ['yapily', 'bridge'],
    ) {
        $indexed = [];
        foreach ($providers as $provider) {
            $indexed[$provider->getName()] = $provider;
        }

        $this->providers = $indexed;
        $this->priority = $providerPriority;
    }

    /**
     * Retourne un provider par son nom.
     *
     * @throws \InvalidArgumentException si le provider n'existe pas
     */
    public function getProvider(string $name): BankProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new \InvalidArgumentException(sprintf('Provider bancaire "%s" inconnu. Disponibles : %s', $name, implode(', ', array_keys($this->providers))));
        }

        return $this->providers[$name];
    }

    /**
     * Retourne le premier provider supportant la banque, dans l'ordre de priorite.
     *
     * @throws \RuntimeException si aucun provider ne supporte la banque
     */
    public function getProviderForBank(string $bankIdentifier, string $countryCode): BankProviderInterface
    {
        foreach ($this->getOrderedProviders() as $provider) {
            if ($provider->isBankSupported($bankIdentifier)) {
                return $provider;
            }
        }

        throw new ExternalServiceException(sprintf('Aucun provider ne supporte la banque "%s" (pays: %s).', $bankIdentifier, $countryCode));
    }

    /**
     * Retourne tous les providers dans l'ordre de priorite.
     *
     * @return list<BankProviderInterface>
     */
    public function getAllProviders(): array
    {
        return $this->getOrderedProviders();
    }

    /**
     * @return list<BankProviderInterface>
     */
    private function getOrderedProviders(): array
    {
        $ordered = [];

        // D'abord les providers dans l'ordre de priorite
        foreach ($this->priority as $name) {
            if (isset($this->providers[$name])) {
                $ordered[] = $this->providers[$name];
            }
        }

        // Puis les providers non mentionnes dans la priorite
        foreach ($this->providers as $name => $provider) {
            if (!\in_array($name, $this->priority, true)) {
                $ordered[] = $provider;
            }
        }

        return $ordered;
    }
}
