<?php

namespace App\Banking;

use App\Banking\DTO\AccountBalance;
use App\Banking\DTO\AuthorizationResult;
use App\Banking\DTO\BankAccountInfo;
use App\Banking\DTO\BankInfo;
use App\Banking\DTO\BankTransactionInfo;
use App\Banking\Event\BankProviderFallbackEvent;
use App\Banking\Exception\NoBankProviderAvailableException;
use App\Banking\Exception\ProviderUnavailableException;
use App\Banking\Exception\UnsupportedBankException;
use App\Banking\Provider\BankProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Chaine de providers avec fallback automatique.
 *
 * Pour chaque operation, essaie les providers dans l'ordre de priorite.
 * Si un provider echoue (UnsupportedBankException ou ProviderUnavailableException),
 * le suivant est tente. Si tous echouent, une NoBankProviderAvailableException
 * est levee.
 */
class BankProviderChain
{
    public function __construct(
        private readonly BankProviderRegistry $registry,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @return list<BankInfo>
     */
    public function getAvailableBanks(string $countryCode): array
    {
        // Agreger les banques de tous les providers (sans doublon par id)
        $allBanks = [];
        $seen = [];

        foreach ($this->registry->getAllProviders() as $provider) {
            try {
                foreach ($provider->getAvailableBanks($countryCode) as $bank) {
                    if (!isset($seen[$bank->id])) {
                        $allBanks[] = $bank;
                        $seen[$bank->id] = true;
                    }
                }
            } catch (ProviderUnavailableException $e) {
                $this->logger->warning('Provider indisponible pour lister les banques.', [
                    'provider' => $provider->getName(),
                    'countryCode' => $countryCode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $allBanks;
    }

    public function createUserAuthorization(
        string $userId,
        string $bankId,
        string $redirectUrl,
    ): AuthorizationResult {
        return $this->executeWithFallback(
            'createUserAuthorization',
            static fn (BankProviderInterface $p): AuthorizationResult => $p->createUserAuthorization($userId, $bankId, $redirectUrl),
        );
    }

    /**
     * @return list<BankAccountInfo>
     */
    public function getAccounts(string $authorizationId): array
    {
        return $this->executeWithFallback(
            'getAccounts',
            static fn (BankProviderInterface $p): array => $p->getAccounts($authorizationId),
        );
    }

    /**
     * @return list<BankTransactionInfo>
     */
    public function getTransactions(
        string $accountId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        return $this->executeWithFallback(
            'getTransactions',
            static fn (BankProviderInterface $p): array => $p->getTransactions($accountId, $from, $to),
        );
    }

    /**
     * @return list<AccountBalance>
     */
    public function getBalances(string $accountId): array
    {
        return $this->executeWithFallback(
            'getBalances',
            static fn (BankProviderInterface $p): array => $p->getBalances($accountId),
        );
    }

    public function refreshConsent(string $authorizationId): AuthorizationResult
    {
        return $this->executeWithFallback(
            'refreshConsent',
            static fn (BankProviderInterface $p): AuthorizationResult => $p->refreshConsent($authorizationId),
        );
    }

    /**
     * Execute une operation sur les providers dans l'ordre, avec fallback.
     *
     * @template T
     *
     * @param callable(BankProviderInterface): T $operation
     *
     * @return T
     *
     * @throws NoBankProviderAvailableException
     */
    private function executeWithFallback(string $operationName, callable $operation): mixed
    {
        $providers = $this->registry->getAllProviders();
        $lastException = null;
        $previousProvider = null;

        foreach ($providers as $provider) {
            try {
                return $operation($provider);
            } catch (UnsupportedBankException|ProviderUnavailableException $e) {
                $this->logger->warning('Fallback bancaire active.', [
                    'failedProvider' => $provider->getName(),
                    'operation' => $operationName,
                    'reason' => $e->getMessage(),
                ]);

                if (null !== $previousProvider) {
                    $this->dispatcher->dispatch(new BankProviderFallbackEvent(
                        failedProvider: $previousProvider,
                        fallbackProvider: $provider->getName(),
                        operation: $operationName,
                        reason: $e->getMessage(),
                    ));
                }

                $previousProvider = $provider->getName();
                $lastException = $e;
            }
        }

        throw new NoBankProviderAvailableException($operationName, $lastException?->getMessage() ?? '');
    }
}
