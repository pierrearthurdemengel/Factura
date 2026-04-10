<?php

namespace App\Banking\Provider;

use App\Banking\DTO\AccountBalance;
use App\Banking\DTO\AuthorizationResult;
use App\Banking\DTO\BankAccountInfo;
use App\Banking\DTO\BankInfo;
use App\Banking\DTO\BankTransactionInfo;
use App\Banking\Exception\ConsentExpiredException;
use App\Banking\Exception\ProviderUnavailableException;
use App\Banking\Exception\UnsupportedBankException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contrat commun pour les providers Open Banking (Yapily, Bridge, etc.).
 *
 * Chaque implementation normalise les reponses de son API vers les DTOs
 * internes. Les exceptions specifiques permettent au BankProviderChain
 * de gerer le fallback automatique.
 */
#[AutoconfigureTag('app.bank_provider')]
interface BankProviderInterface
{
    /**
     * Identifiant unique du provider (ex: 'yapily', 'bridge').
     */
    public function getName(): string;

    /**
     * Liste des banques disponibles pour un code pays ISO 3166-1 alpha-2.
     *
     * @return list<BankInfo>
     *
     * @throws ProviderUnavailableException
     */
    public function getAvailableBanks(string $countryCode): array;

    /**
     * Verifie si une banque est supportee par ce provider.
     */
    public function isBankSupported(string $bankIdentifier): bool;

    /**
     * Initie le flow OAuth2 de connexion bancaire PSD2.
     *
     * @throws UnsupportedBankException
     * @throws ProviderUnavailableException
     */
    public function createUserAuthorization(
        string $userId,
        string $bankId,
        string $redirectUrl,
    ): AuthorizationResult;

    /**
     * Recupere les comptes lies a une autorisation.
     *
     * @return list<BankAccountInfo>
     *
     * @throws ConsentExpiredException
     * @throws ProviderUnavailableException
     */
    public function getAccounts(string $authorizationId): array;

    /**
     * Recupere les transactions d'un compte sur une periode.
     *
     * @return list<BankTransactionInfo>
     *
     * @throws ConsentExpiredException
     * @throws ProviderUnavailableException
     */
    public function getTransactions(
        string $accountId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array;

    /**
     * Recupere les soldes d'un compte.
     *
     * @return list<AccountBalance>
     *
     * @throws ConsentExpiredException
     * @throws ProviderUnavailableException
     */
    public function getBalances(string $accountId): array;

    /**
     * Renouvelle le consentement PSD2 expire.
     *
     * @throws ProviderUnavailableException
     */
    public function refreshConsent(string $authorizationId): AuthorizationResult;
}
