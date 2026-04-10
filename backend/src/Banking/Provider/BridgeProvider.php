<?php

namespace App\Banking\Provider;

use App\Banking\DTO\AccountBalance;
use App\Banking\DTO\AuthorizationResult;
use App\Banking\DTO\AuthorizationStatus;
use App\Banking\DTO\BalanceType;
use App\Banking\DTO\BankAccountInfo;
use App\Banking\DTO\BankInfo;
use App\Banking\DTO\BankTransactionInfo;
use App\Banking\DTO\TransactionType;
use App\Banking\Exception\ConsentExpiredException;
use App\Banking\Exception\ProviderUnavailableException;
use App\Banking\Exception\UnsupportedBankException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Provider Open Banking via l'API Bridge (Bankin').
 *
 * Bridge offre une bonne couverture France/Espagne/Italie et
 * sert de provider de fallback apres Yapily.
 *
 * @see https://docs.bridgeapi.io/
 */
class BridgeProvider implements BankProviderInterface
{
    private const BASE_URL = 'https://api.bridgeapi.io/v3';
    private const CACHE_TTL = 86400;

    private ?string $bearerToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $bridgeClientId,
        private readonly string $bridgeClientSecret,
    ) {
    }

    public function getName(): string
    {
        return 'bridge';
    }

    public function getAvailableBanks(string $countryCode): array
    {
        $cacheKey = sprintf('bank_provider.bridge.banks.%s', strtolower($countryCode));

        /** @var list<BankInfo> */
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($countryCode): array {
            $item->expiresAfter(self::CACHE_TTL);

            $response = $this->request('GET', '/banks', [
                'query' => ['country' => strtoupper($countryCode)],
            ]);

            return array_map(
                fn (array $bank): BankInfo => new BankInfo(
                    id: (string) ($bank['id'] ?? ''),
                    name: $bank['name'] ?? '',
                    countryCode: $countryCode,
                    logoUrl: $bank['logo_url'] ?? null,
                    providerName: $this->getName(),
                ),
                $response['resources'] ?? [],
            );
        });
    }

    public function isBankSupported(string $bankIdentifier): bool
    {
        try {
            $response = $this->request('GET', sprintf('/banks/%s', $bankIdentifier));

            return isset($response['id']);
        } catch (ProviderUnavailableException) {
            return false;
        }
    }

    public function createUserAuthorization(
        string $userId,
        string $bankId,
        string $redirectUrl,
    ): AuthorizationResult {
        if (!$this->isBankSupported($bankId)) {
            throw new UnsupportedBankException($bankId, $this->getName());
        }

        $response = $this->request('POST', '/connect/items/add', [
            'json' => [
                'bank_id' => (int) $bankId,
                'redirect_url' => $redirectUrl,
                'user_uuid' => $userId,
            ],
        ]);

        return new AuthorizationResult(
            authorizationId: (string) ($response['id'] ?? ''),
            redirectUrl: $response['redirect_url'] ?? null,
            status: AuthorizationStatus::Pending,
            expiresAt: new \DateTimeImmutable('+90 days'),
            providerName: $this->getName(),
        );
    }

    public function getAccounts(string $authorizationId): array
    {
        $response = $this->request('GET', '/accounts', [
            'query' => ['item_id' => $authorizationId],
        ]);

        if (402 === ($response['status'] ?? 0)) {
            throw new ConsentExpiredException($authorizationId, $this->getName());
        }

        return array_values(array_map(
            fn (array $acc): BankAccountInfo => new BankAccountInfo(
                id: (string) ($acc['id'] ?? ''),
                iban: $acc['iban'] ?? null,
                currency: $acc['currency_code'] ?? 'EUR',
                type: $acc['type'] ?? 'checking',
                providerName: $this->getName(),
                providerAccountId: (string) ($acc['id'] ?? ''),
            ),
            $response['resources'] ?? [],
        ));
    }

    public function getTransactions(
        string $accountId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        $response = $this->request('GET', sprintf('/accounts/%s/transactions', $accountId), [
            'query' => [
                'since' => $from->format('Y-m-d'),
                'until' => $to->format('Y-m-d'),
            ],
        ]);

        return array_values(array_map(
            fn (array $tx): BankTransactionInfo => new BankTransactionInfo(
                id: (string) ($tx['id'] ?? ''),
                amount: (string) abs((float) ($tx['amount'] ?? 0)),
                currency: $tx['currency_code'] ?? 'EUR',
                date: new \DateTimeImmutable($tx['date'] ?? 'now'),
                description: $tx['description'] ?? $tx['raw_description'] ?? '',
                reference: $tx['bank_description'] ?? null,
                type: ((float) ($tx['amount'] ?? 0)) >= 0
                    ? TransactionType::Credit
                    : TransactionType::Debit,
                category: isset($tx['category']) ? (string) $tx['category']['id'] : null,
                providerName: $this->getName(),
                providerTransactionId: (string) ($tx['id'] ?? ''),
            ),
            $response['resources'] ?? [],
        ));
    }

    public function getBalances(string $accountId): array
    {
        // Bridge retourne le solde directement sur le compte
        $response = $this->request('GET', sprintf('/accounts/%s', $accountId));

        $balance = $response['balance'] ?? null;

        if (null === $balance) {
            return [];
        }

        return [
            new AccountBalance(
                amount: (string) $balance,
                currency: $response['currency_code'] ?? 'EUR',
                type: BalanceType::Booked,
                updatedAt: new \DateTimeImmutable($response['updated_at'] ?? 'now'),
            ),
        ];
    }

    public function refreshConsent(string $authorizationId): AuthorizationResult
    {
        $response = $this->request('POST', sprintf('/connect/items/%s/refresh', $authorizationId));

        return new AuthorizationResult(
            authorizationId: (string) ($response['id'] ?? $authorizationId),
            redirectUrl: $response['redirect_url'] ?? null,
            status: AuthorizationStatus::Pending,
            expiresAt: new \DateTimeImmutable('+90 days'),
            providerName: $this->getName(),
        );
    }

    /**
     * Effectue une requete HTTP vers l'API Bridge avec authentification Bearer.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $token = $this->getAccessToken();

        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            [
                'Accept' => 'application/json',
                'Bridge-Version' => '2021-06-01',
                'Authorization' => 'Bearer ' . $token,
            ],
        );

        try {
            $response = $this->httpClient->request($method, self::BASE_URL . $path, $options);

            /** @var array<string, mixed> */
            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Erreur API Bridge', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new ProviderUnavailableException($this->getName(), $e->getMessage(), $e);
        }
    }

    /**
     * Obtient un token d'acces Bridge via client credentials.
     */
    private function getAccessToken(): string
    {
        if (null !== $this->bearerToken) {
            return $this->bearerToken;
        }

        try {
            $response = $this->httpClient->request('POST', self::BASE_URL . '/authenticate', [
                'json' => [
                    'client_id' => $this->bridgeClientId,
                    'client_secret' => $this->bridgeClientSecret,
                ],
            ]);

            /** @var array{access_token: string} $data */
            $data = $response->toArray();
            $this->bearerToken = $data['access_token'];

            return $this->bearerToken;
        } catch (\Throwable $e) {
            throw new ProviderUnavailableException($this->getName(), 'Echec authentification', $e);
        }
    }
}
