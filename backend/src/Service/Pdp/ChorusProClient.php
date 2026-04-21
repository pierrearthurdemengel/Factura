<?php

namespace App\Service\Pdp;

use App\Entity\Invoice;
use App\Exception\PdpTransmissionException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client PDP Chorus Pro via la plateforme PISTE (API REST, auth OAuth2).
 *
 * Chorus Pro est la plateforme gratuite de l'Etat, obligatoire pour les factures B2G.
 * L'authentification utilise un token OAuth2 PISTE + un header cpro-account
 * contenant les identifiants du compte technique encodes en base64.
 */
class ChorusProClient implements PdpClientInterface
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $chorusProBaseUrl,
        private readonly string $chorusProOauthUrl,
        private readonly string $chorusProClientId,
        private readonly string $chorusProClientSecret,
        private readonly string $chorusProTechLogin,
        private readonly string $chorusProTechPassword,
    ) {
    }

    public function transmit(Invoice $invoice, string $xmlContent, string $format): string
    {
        try {
            $payload = $this->buildSubmitPayload($invoice);

            $data = $this->callApi('/cpro/factures/v1/soumettre', $payload);

            if (0 !== (int) ($data['codeRetour'] ?? -1)) {
                throw new \RuntimeException(sprintf('codeRetour=%s : %s', $data['codeRetour'] ?? '?', $data['libelle'] ?? 'Erreur inconnue'));
            }

            return (string) $data['identifiantFactureCPP'];
        } catch (PdpTransmissionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new PdpTransmissionException($this->getName(), $invoice->getNumber() ?? 'N/A', $e->getMessage());
        }
    }

    public function getStatus(string $pdpReference): PdpStatus
    {
        try {
            $data = $this->callApi('/cpro/factures/v1/consulter', [
                'identifiantFactureCPP' => (int) $pdpReference,
            ]);

            $status = $data['statutFacture'] ?? 'INCONNU';

            return match ($status) {
                'DEPOSEE', 'EN_COURS_ACHEMINEMENT' => PdpStatus::PENDING,
                'MISE_A_DISPOSITION', 'MISE_EN_PAIEMENT' => PdpStatus::RECEIVED,
                'ACCEPTEE', 'VALIDEE' => PdpStatus::ACKNOWLEDGED,
                'REFUSEE', 'REJETEE' => PdpStatus::REJECTED,
                default => PdpStatus::ERROR,
            };
        } catch (\Throwable) {
            return PdpStatus::ERROR;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchIncomingInvoices(\DateTimeImmutable $since): array
    {
        try {
            $data = $this->callApi('/cpro/factures/v1/rechercher/fournisseur', [
                'dateDepotDebut' => $since->format('Y-m-d'),
                'parametres' => [
                    'nbResultatsParPage' => 100,
                    'pageResultatDemandee' => 1,
                ],
            ]);

            return $data['listeFactures'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function getName(): string
    {
        return 'chorus_pro';
    }

    /**
     * Recherche une structure par SIRET dans le referentiel Chorus Pro.
     *
     * @return array<string, mixed>|null
     */
    public function rechercherStructure(string $siret): ?array
    {
        $data = $this->callApi('/cpro/structures/v1/rechercher', [
            'parametres' => [
                'nbResultatsParPage' => 1,
                'pageResultatDemandee' => 1,
            ],
            'restreindreStructuresPrivees' => false,
            'structure' => [
                'identifiantStructure' => $siret,
                'typeIdentifiantStructure' => 'SIRET',
            ],
        ]);

        $structures = $data['listeStructures'] ?? [];

        return $structures[0] ?? null;
    }

    /**
     * Construit le payload JSON pour soumettreFacture a partir de l'entite Invoice.
     *
     * @return array<string, mixed>
     */
    private function buildSubmitPayload(Invoice $invoice): array
    {
        $seller = $invoice->getSeller();
        $buyer = $invoice->getBuyer();

        if (null === $seller) {
            throw new \RuntimeException('La facture doit avoir un vendeur pour la transmission Chorus Pro.');
        }

        // Recherche de l'idStructureCPP du fournisseur via son SIRET
        $sellerSiret = $seller->getSiret() ?? $seller->getSiren();
        $sellerStructure = $this->rechercherStructure($sellerSiret);

        if (null === $sellerStructure) {
            throw new \RuntimeException(sprintf('Structure fournisseur introuvable dans Chorus Pro (SIRET=%s)', $sellerSiret));
        }

        $idFournisseur = (int) $sellerStructure['idStructureCPP'];

        // Le code destinataire est le SIRET du client
        $codeDestinataire = $buyer->getSiret() ?? $buyer->getSiren() ?? '';

        // Construction des lignes de poste
        $lignesPoste = [];
        foreach ($invoice->getLines() as $line) {
            $lignesPoste[] = [
                'lignePosteNumero' => $line->getPosition(),
                'lignePosteReference' => 'L' . $line->getPosition(),
                'lignePosteDenomination' => $line->getDescription(),
                'lignePosteQuantite' => (float) $line->getQuantity(),
                'lignePosteUnite' => $this->mapUnit($line->getUnit()),
                'lignePosteMontantUnitaireHT' => (float) $line->getUnitPriceExcludingTax(),
                'lignePosteTauxTvaManuel' => (float) $line->getVatRate(),
            ];
        }

        // Regroupement des lignes TVA par taux
        $tvaParTaux = [];
        foreach ($invoice->getLines() as $line) {
            $taux = $line->getVatRate();
            if (!isset($tvaParTaux[$taux])) {
                $tvaParTaux[$taux] = ['base' => '0.00', 'montant' => '0.00'];
            }
            $lineAmount = $line->getLineAmount();
            $vatAmount = $line->getVatAmount();
            \assert(is_numeric($lineAmount));
            \assert(is_numeric($vatAmount));
            $tvaParTaux[$taux]['base'] = bcadd($tvaParTaux[$taux]['base'], $lineAmount, 2);
            $tvaParTaux[$taux]['montant'] = bcadd($tvaParTaux[$taux]['montant'], $vatAmount, 2);
        }

        $lignesTva = [];
        foreach ($tvaParTaux as $taux => $montants) {
            $lignesTva[] = [
                'ligneTvaTauxManuel' => (float) $taux,
                'ligneTvaMontantBaseHtParTaux' => (float) $montants['base'],
                'ligneTvaMontantTvaParTaux' => (float) $montants['montant'],
            ];
        }

        return [
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
                'deviseFacture' => $invoice->getCurrency(),
                'typeFacture' => 'FACTURE',
                'typeTva' => 'TVA_SUR_DEBIT',
                'modePaiement' => 'VIREMENT',
                'dateFacture' => $invoice->getIssueDate()->format('Y-m-d'),
            ],
            'lignePoste' => $lignesPoste,
            'ligneTva' => $lignesTva,
            'montantTotal' => [
                'montantHtTotal' => (float) $invoice->getTotalExcludingTax(),
                'montantTVA' => (float) $invoice->getTotalTax(),
                'montantAPayer' => (float) $invoice->getTotalIncludingTax(),
            ],
        ];
    }

    /**
     * Appelle un endpoint Chorus Pro avec authentification PISTE (OAuth2 + cpro-account).
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function callApi(string $path, array $payload): array
    {
        $token = $this->getAccessToken();
        $cproAccount = base64_encode($this->chorusProTechLogin . ':' . $this->chorusProTechPassword);

        $url = rtrim($this->chorusProBaseUrl, '/') . $path;

        $response = $this->client->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'cpro-account' => $cproAccount,
                'Accept' => 'application/json',
            ],
            'json' => $payload,
        ]);

        return $response->toArray(false);
    }

    /**
     * Obtient un token OAuth2 PISTE via le flux client_credentials.
     * Le token est mis en cache pour la duree de vie de l'instance.
     */
    private function getAccessToken(): string
    {
        if (null !== $this->accessToken) {
            return $this->accessToken;
        }

        $response = $this->client->request('POST', $this->chorusProOauthUrl, [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->chorusProClientId,
                'client_secret' => $this->chorusProClientSecret,
                'scope' => 'openid',
            ],
        ]);

        $data = $response->toArray();
        $this->accessToken = $data['access_token'] ?? '';

        if ('' === $this->accessToken) {
            throw new \RuntimeException('Impossible d\'obtenir un token OAuth2 PISTE.');
        }

        return $this->accessToken;
    }

    /**
     * Convertit le code unite interne vers le libelle Chorus Pro.
     */
    private function mapUnit(string $unit): string
    {
        return match ($unit) {
            'EA' => 'unite',
            'HUR' => 'heure',
            'DAY' => 'jour',
            default => 'lot',
        };
    }
}
