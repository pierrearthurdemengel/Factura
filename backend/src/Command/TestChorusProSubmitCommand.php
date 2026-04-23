<?php

namespace App\Command;

use App\Service\Pdp\ChorusProClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Commande de test pour valider la connexion et le depot de facture
 * sur le sandbox Chorus Pro via PISTE.
 */
#[AsCommand(
    name: 'app:chorus-pro:test-submit',
    description: 'Teste la connexion et le depot de facture sur le sandbox Chorus Pro',
)]
class TestChorusProSubmitCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ChorusProClient $chorusProClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $baseUrl = $_ENV['CHORUS_PRO_BASE_URL'] ?? '';
        $techLogin = $_ENV['CHORUS_PRO_TECH_LOGIN'] ?? '';
        $techPassword = $_ENV['CHORUS_PRO_TECH_PASSWORD'] ?? '';

        // --- Etape 1 : Token OAuth2 ---
        $accessToken = $this->authenticateOAuth($io);
        if (null === $accessToken) {
            return Command::FAILURE;
        }

        $cproAccount = base64_encode($techLogin . ':' . $techPassword);

        // --- Etape 2 : Recherche structure fournisseur ---
        $idFournisseur = $this->findSupplierStructure($io);
        if (null === $idFournisseur) {
            return Command::FAILURE;
        }

        // --- Etape 3 : Recherche d'un destinataire ---
        $codeDestinataire = $this->findRecipient($io, $baseUrl, $accessToken, $cproAccount);

        // --- Etape 4 : soumettreFacture ---
        return $this->submitInvoice($io, $baseUrl, $accessToken, $cproAccount, $idFournisseur, $codeDestinataire);
    }

    /**
     * Authentification OAuth2 PISTE et retour du token.
     */
    private function authenticateOAuth(SymfonyStyle $io): ?string
    {
        $oauthUrl = $_ENV['CHORUS_PRO_OAUTH_URL'] ?? '';
        $clientId = $_ENV['CHORUS_PRO_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['CHORUS_PRO_CLIENT_SECRET'] ?? '';

        $io->section('1. Authentification OAuth2 PISTE');

        try {
            $tokenResponse = $this->client->request('POST', $oauthUrl, [
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'openid',
                ],
            ]);
            $tokenData = $tokenResponse->toArray();
            $accessToken = $tokenData['access_token'] ?? '';

            if ('' === $accessToken) {
                $io->error('Token OAuth2 vide.');

                return null;
            }

            $io->success(sprintf(
                'Token obtenu (scope: %s, expires_in: %ss)',
                $tokenData['scope'] ?? 'N/A',
                $tokenData['expires_in'] ?? '?'
            ));

            return $accessToken;
        } catch (\Throwable $e) {
            $io->error('Erreur OAuth2 : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Recherche la structure fournisseur via ChorusProClient.
     */
    private function findSupplierStructure(SymfonyStyle $io): ?int
    {
        $io->section('2. Recherche structure fournisseur (via ChorusProClient)');

        try {
            $structure = $this->chorusProClient->rechercherStructure('31582210396351');

            if (null === $structure) {
                $io->error('Structure fournisseur introuvable.');

                return null;
            }

            $idFournisseur = (int) $structure['idStructureCPP'];
            $io->success(sprintf(
                '%s (idStructureCPP=%d, SIRET=%s)',
                $structure['designationStructure'] ?? 'N/A',
                $idFournisseur,
                $structure['identifiantStructure'] ?? 'N/A'
            ));

            return $idFournisseur;
        } catch (\Throwable $e) {
            $io->error('Erreur recherche structure : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Recherche un destinataire dans le sandbox Chorus Pro.
     */
    private function findRecipient(SymfonyStyle $io, string $baseUrl, string $accessToken, string $cproAccount): string
    {
        $io->section('3. Recherche d\'un destinataire');

        $destResponse = $this->client->request('POST', rtrim($baseUrl, '/') . '/cpro/structures/v1/rechercher', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'cpro-account' => $cproAccount,
                'Accept' => 'application/json',
            ],
            'json' => [
                'parametres' => [
                    'nbResultatsParPage' => 3,
                    'pageResultatDemandee' => 1,
                ],
                'restreindreStructuresPrivees' => false,
                'structure' => [
                    'statutStructure' => 'ACTIF',
                ],
            ],
        ]);
        $destData = $destResponse->toArray(false);
        $destinataires = $destData['listeStructures'] ?? [];

        // Choisir un destinataire qui n'est pas le fournisseur
        foreach ($destinataires as $dest) {
            if (($dest['identifiantStructure'] ?? '') !== '31582210396351') {
                $io->success(sprintf(
                    '%s (SIRET=%s)',
                    $dest['designationStructure'] ?? 'N/A',
                    $dest['identifiantStructure']
                ));

                return $dest['identifiantStructure'];
            }
        }

        $io->warning('Pas de destinataire distinct, utilisation d\'un SIRET par defaut.');

        return '99986401570264';
    }

    /**
     * Soumet la facture de test sur le sandbox Chorus Pro.
     */
    private function submitInvoice(SymfonyStyle $io, string $baseUrl, string $accessToken, string $cproAccount, int $idFournisseur, string $codeDestinataire): int
    {
        $io->section('4. soumettreFacture');

        $numero = 'FA-TEST-' . date('YmdHis');
        $io->text('Numero : ' . $numero);

        $submitPayload = [
            'modeDepot' => 'SAISIE_API',
            'destinataire' => [
                'codeDestinataire' => $codeDestinataire,
            ],
            'fournisseur' => [
                'idFournisseur' => $idFournisseur,
            ],
            'cadreDeFacturation' => [
                'codeCadreFacturation' => 'A1_FACTURE_FOURNISSEUR',
            ],
            'references' => [
                'deviseFacture' => 'EUR',
                'typeFacture' => 'FACTURE',
                'typeTva' => 'TVA_SUR_DEBIT',
                'modePaiement' => 'VIREMENT',
            ],
            'lignePoste' => [
                [
                    'lignePosteNumero' => 1,
                    'lignePosteReference' => 'PREST-001',
                    'lignePosteDenomination' => 'Prestation de conseil informatique',
                    'lignePosteQuantite' => 1,
                    'lignePosteUnite' => 'lot',
                    'lignePosteMontantUnitaireHT' => 100.000000,
                    'lignePosteTauxTvaManuel' => 20.00,
                ],
            ],
            'ligneTva' => [
                [
                    'ligneTvaTauxManuel' => 20.00,
                    'ligneTvaMontantBaseHtParTaux' => 100.000000,
                    'ligneTvaMontantTvaParTaux' => 20.000000,
                ],
            ],
            'montantTotal' => [
                'montantHtTotal' => 100.000000,
                'montantTVA' => 20.000000,
                'montantAPayer' => 120.000000,
            ],
        ];

        $submitResponse = $this->client->request('POST', rtrim($baseUrl, '/') . '/cpro/factures/v1/soumettre', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'cpro-account' => $cproAccount,
                'Accept' => 'application/json',
            ],
            'json' => $submitPayload,
        ]);

        $submitData = $submitResponse->toArray(false);
        $io->text((string) json_encode($submitData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));

        if (isset($submitData['identifiantFactureCPP']) && (int) $submitData['identifiantFactureCPP'] > 0) {
            $io->success(sprintf(
                'Facture soumise ! identifiantFactureCPP=%s, numero=%s, statut=%s',
                $submitData['identifiantFactureCPP'],
                $submitData['numeroFacture'] ?? 'N/A',
                $submitData['statutFacture'] ?? 'N/A'
            ));

            return Command::SUCCESS;
        }

        $io->error(sprintf(
            'Erreur soumission [%s] : %s',
            $submitData['codeRetour'] ?? '?',
            $submitData['libelle'] ?? 'Reponse inattendue'
        ));

        return Command::FAILURE;
    }
}
