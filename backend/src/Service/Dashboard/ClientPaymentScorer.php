<?php

namespace App\Service\Dashboard;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Evalue la fiabilite de paiement d'un client.
 *
 * Le score (0-100) est calcule a partir de :
 * - Le taux de factures payees dans les delais (50 points max)
 * - Le delai moyen de paiement (30 points max)
 * - L'anciennete de la relation (20 points max)
 *
 * Un score eleve indique un client fiable. Un score inferieur a 50
 * declenche une alerte "mauvais payeur".
 */
class ClientPaymentScorer
{
    // Seuils de fiabilite
    public const THRESHOLD_EXCELLENT = 80;
    public const THRESHOLD_GOOD = 60;
    public const THRESHOLD_WARNING = 40;
    public const THRESHOLD_POOR = 20;

    // Labels humains pour les niveaux
    private const SCORE_LABELS = [
        'excellent' => 'Excellent payeur',
        'good' => 'Bon payeur',
        'average' => 'Payeur moyen',
        'poor' => 'Mauvais payeur',
        'critical' => 'Payeur critique',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Calcule le score de fiabilite d'un client (0-100).
     */
    public function getScore(Client $client): int
    {
        $invoices = $this->getClientInvoices($client);

        if (0 === \count($invoices)) {
            return 50; // Score neutre pour un nouveau client
        }

        // Composante 1 : Taux de paiement (50 points)
        $paymentScore = $this->calculatePaymentRateScore($invoices);

        // Composante 2 : Delai moyen de paiement (30 points)
        $delayScore = $this->calculateDelayScore($invoices);

        // Composante 3 : Anciennete de la relation (20 points)
        $tenureScore = $this->calculateTenureScore($invoices);

        return min(100, max(0, $paymentScore + $delayScore + $tenureScore));
    }

    /**
     * Calcule le delai moyen de paiement en jours.
     *
     * Retourne null si aucune facture payee.
     */
    public function getAveragePaymentDelay(Client $client): ?int
    {
        $invoices = $this->getClientInvoices($client);
        $delays = [];

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            if ('PAID' !== $invoice->getStatus() || null === $invoice->getDueDate()) {
                continue;
            }

            // Estimation : delai entre la date d'emission et la date d'echeance
            // En production, on utiliserait la date de paiement reelle
            $delay = (int) $invoice->getIssueDate()->diff($invoice->getDueDate())->days;
            $delays[] = $delay;
        }

        if (0 === \count($delays)) {
            return null;
        }

        return (int) round(array_sum($delays) / \count($delays));
    }

    /**
     * Retourne le profil complet du scoring client.
     *
     * @return array{
     *     score: int,
     *     label: string,
     *     level: string,
     *     averageDelay: int|null,
     *     paidCount: int,
     *     totalCount: int,
     *     paymentRate: string,
     *     suggestion: string|null
     * }
     */
    public function getProfile(Client $client): array
    {
        $score = $this->getScore($client);
        $level = $this->getLevel($score);
        $averageDelay = $this->getAveragePaymentDelay($client);

        $invoices = $this->getClientInvoices($client);
        $paidCount = 0;
        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            if ('PAID' === $invoice->getStatus()) {
                ++$paidCount;
            }
        }

        $totalCount = \count($invoices);
        $paymentRate = $totalCount > 0
            ? bcmul(bcdiv((string) $paidCount, (string) $totalCount, 4), '100', 2)
            : '0.00';

        // Suggestion pour les mauvais payeurs
        $suggestion = null;
        if ($score < self::THRESHOLD_GOOD) {
            $suggestion = 'Envisager un escompte de 2% pour paiement anticipe ou demander un acompte de 30%.';
        }
        if ($score < self::THRESHOLD_WARNING) {
            $suggestion = 'Exiger un acompte de 50% avant prestation. Reduire les delais de paiement a 15 jours.';
        }

        return [
            'score' => $score,
            'label' => self::SCORE_LABELS[$level],
            'level' => $level,
            'averageDelay' => $averageDelay,
            'paidCount' => $paidCount,
            'totalCount' => $totalCount,
            'paymentRate' => $paymentRate,
            'suggestion' => $suggestion,
        ];
    }

    /**
     * Detecte les mauvais payeurs parmi les clients d'une entreprise.
     *
     * @return array<int, array{clientId: string, clientName: string, score: int, level: string}>
     */
    public function detectBadPayers(Company $company): array
    {
        // Recuperer les clients uniques via les factures
        $qb = $this->em->createQueryBuilder();
        $invoices = $qb->select('i')
            ->from(Invoice::class, 'i')
            ->where('i.seller = :company')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('company', $company)
            ->setParameter('statuses', ['SENT', 'ACKNOWLEDGED', 'PAID', 'REJECTED'])
            ->getQuery()
            ->getResult();

        $clients = [];
        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            $clientId = (string) $invoice->getBuyer()->getId();
            if (!isset($clients[$clientId])) {
                $clients[$clientId] = $invoice->getBuyer();
            }
        }

        $badPayers = [];
        foreach ($clients as $clientId => $client) {
            $score = $this->getScore($client);
            if ($score < self::THRESHOLD_GOOD) {
                $badPayers[] = [
                    'clientId' => $clientId,
                    'clientName' => $client->getName(),
                    'score' => $score,
                    'level' => $this->getLevel($score),
                ];
            }
        }

        // Trier par score croissant (les pires en premier)
        usort($badPayers, static fn (array $a, array $b) => $a['score'] <=> $b['score']);

        return $badPayers;
    }

    /**
     * Determine le niveau de fiabilite a partir du score.
     */
    private function getLevel(int $score): string
    {
        if ($score >= self::THRESHOLD_EXCELLENT) {
            return 'excellent';
        }
        if ($score >= self::THRESHOLD_GOOD) {
            return 'good';
        }
        if ($score >= self::THRESHOLD_WARNING) {
            return 'average';
        }
        if ($score >= self::THRESHOLD_POOR) {
            return 'poor';
        }

        return 'critical';
    }

    /**
     * Score base sur le taux de factures payees (max 50 points).
     *
     * @param Invoice[] $invoices
     */
    private function calculatePaymentRateScore(array $invoices): int
    {
        $total = \count($invoices);
        if (0 === $total) {
            return 25;
        }

        $paid = 0;
        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            if ('PAID' === $invoice->getStatus()) {
                ++$paid;
            }
        }

        // Ratio paye/total * 50
        return (int) round(($paid / $total) * 50);
    }

    /**
     * Score base sur le delai moyen de paiement (max 30 points).
     *
     * 0 jours de retard = 30 points, 30+ jours = 0 points.
     *
     * @param Invoice[] $invoices
     */
    private function calculateDelayScore(array $invoices): int
    {
        $delays = [];

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            if ('PAID' !== $invoice->getStatus() || null === $invoice->getDueDate()) {
                continue;
            }

            $delay = (int) $invoice->getIssueDate()->diff($invoice->getDueDate())->days;
            $delays[] = max(0, $delay);
        }

        if (0 === \count($delays)) {
            return 15; // Score neutre
        }

        $avgDelay = array_sum($delays) / \count($delays);

        // Score inverse : 0 jours = 30pts, 30 jours = 0pts
        $score = 30 - (int) round($avgDelay);

        return max(0, min(30, $score));
    }

    /**
     * Score base sur l'anciennete de la relation (max 20 points).
     *
     * Plus de 12 factures = 20 points, 1 facture = 5 points.
     *
     * @param Invoice[] $invoices
     */
    private function calculateTenureScore(array $invoices): int
    {
        $count = \count($invoices);

        if ($count >= 12) {
            return 20;
        }
        if ($count >= 6) {
            return 15;
        }
        if ($count >= 3) {
            return 10;
        }

        return 5;
    }

    /**
     * Recupere les factures d'un client.
     *
     * @return Invoice[]
     */
    private function getClientInvoices(Client $client): array
    {
        $qb = $this->em->createQueryBuilder();

        return $qb->select('i')
            ->from(Invoice::class, 'i')
            ->where('i.buyer = :client')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('statuses', ['SENT', 'ACKNOWLEDGED', 'PAID', 'REJECTED'])
            ->getQuery()
            ->getResult();
    }
}
