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
 * Provider Open Banking via l'API Yapily.
 *
 * Yapily offre une couverture europeenne large (2000+ banques)
 * et sert de provider prioritaire.
 *
 * @see https://docs.yapily.com/
 */
class YapilyProvider implements BankProviderInterface
{
    private const BASE_URL = 'https://api.yapily.com';
    private const CACHE_TTL = 86400; // 24 heures

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $yapilyAppId,
        private readonly string $yapilyAppSecret,
    ) {
    }

    public function getName(): string
    {
        return 'yapily';
    }

    public function getAvailableBanks(string $countryCode): array
    {
        $cacheKey = sprintf('bank_provider.yapily.banks.%s', strtolower($countryCode));

        /** @var list<BankInfo> */
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($countryCode): array {
            $item->expiresAfter(self::CACHE_TTL);

            $response = $this->request('GET', '/institutions', [
                'query' => ['country' => strtoupper($countryCode)],
            ]);

            return array_map(
                fn (array $inst): BankInfo => new BankInfo(
                    id: $inst['id'],
                    name: $inst['name'] ?? $inst['id'],
                    countryCode: $countryCode,
                    logoUrl: $inst['media'][0]['source'] ?? null,
                    providerName: $this->getName(),
                ),
                $response['data'] ?? [],
            );
        });
    }

    public function isBankSupported(string $bankIdentifier): bool
    {
        try {
            $response = $this->request('GET', sprintf('/institutions/%s', $bankIdentifier));

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

        $response = $this->request('POST', '/account-auth-requests', [
            'json' => [
                'applicationUserId' => $userId,
                'institutionId' => $bankId,
                'callback' => $redirectUrl,
            ],
        ]);

        $data = $response['data'] ?? $response;

        return new AuthorizationResult(
            authorizationId: $data['id'] ?? '',
            redirectUrl: $data['authorisationUrl'] ?? null,
            status: AuthorizationStatus::Pending,
            expiresAt: isset($data['expiresAt'])
                ? new \DateTimeImmutable($data['expiresAt'])
                : new \DateTimeImmutable('+90 days'),
            providerName: $this->getName(),
        );
    }

    public function getAccounts(string $authorizationId): array
    {
        $response = $this->request('GET', '/accounts', [
            'headers' => ['consent' => $authorizationId],
        ]);

        if (isset($response['error']) && 'CONSENT_EXPIRED' === ($response['error']['code'] ?? '')) {
            throw new ConsentExpiredException($authorizationId, $this->getName());
        }

        return array_values(array_map(
            fn (array $acc): BankAccountInfo => new BankAccountInfo(
                id: $acc['id'],
                iban: $acc['accountIdentifications'][0]['identification'] ?? null,
                currency: $acc['currency'] ?? 'EUR',
                type: $acc['type'] ?? 'CURRENT',
                providerName: $this->getName(),
                providerAccountId: $acc['id'],
            ),
            $response['data'] ?? [],
        ));
    }

    public function getTransactions(
        string $accountId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        $response = $this->request('GET', sprintf('/accounts/%s/transactions', $accountId), [
            'query' => [
                'from' => $from->format('Y-m-d'),
                'before' => $to->format('Y-m-d'),
            ],
        ]);

        return array_values(array_map(
            fn (array $tx): BankTransactionInfo => new BankTransactionInfo(
                id: $tx['id'] ?? '',
                amount: (string) abs((float) ($tx['amount'] ?? 0)),
                currency: $tx['currency'] ?? 'EUR',
                date: new \DateTimeImmutable($tx['date'] ?? 'now'),
                description: $tx['description'] ?? '',
                reference: $tx['reference'] ?? null,
                type: ((float) ($tx['amount'] ?? 0)) >= 0
                    ? TransactionType::Credit
                    : TransactionType::Debit,
                category: $tx['enrichment']['categorisation']['category'] ?? null,
                providerName: $this->getName(),
                providerTransactionId: $tx['id'] ?? '',
            ),
            $response['data'] ?? [],
        ));
    }

    public function getBalances(string $accountId): array
    {
        $response = $this->request('GET', sprintf('/accounts/%s/balances', $accountId));

        return array_values(array_map(
            fn (array $bal): AccountBalance => new AccountBalance(
                amount: (string) ($bal['balanceAmount']['amount'] ?? '0'),
                currency: $bal['balanceAmount']['currency'] ?? 'EUR',
                type: 'EXPECTED' === ($bal['type'] ?? '')
                    ? BalanceType::Available
                    : BalanceType::Booked,
                updatedAt: new \DateTimeImmutable($bal['dateTime'] ?? 'now'),
            ),
            $response['data'] ?? [],
        ));
    }

    public function refreshConsent(string $authorizationId): AuthorizationResult
    {
        $response = $this->request('PATCH', sprintf('/account-auth-requests/%s', $authorizationId), [
            'json' => ['status' => 'AUTHORIZED'],
        ]);

        $data = $response['data'] ?? $response;

        return new AuthorizationResult(
            authorizationId: $data['id'] ?? $authorizationId,
            redirectUrl: $data['authorisationUrl'] ?? null,
            status: AuthorizationStatus::Pending,
            expiresAt: new \DateTimeImmutable('+90 days'),
            providerName: $this->getName(),
        );
    }

    /**
     * Effectue une requete HTTP vers l'API Yapily avec authentification Basic.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $options['auth_basic'] = [$this->yapilyAppId, $this->yapilyAppSecret];
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            ['Accept' => 'application/json'],
        );

        try {
            $response = $this->httpClient->request($method, self::BASE_URL . $path, $options);

            /** @var array<string, mixed> */
            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Erreur API Yapily', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new ProviderUnavailableException($this->getName(), $e->getMessage(), $e);
        }
    }
}
