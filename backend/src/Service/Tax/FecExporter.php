<?php

namespace App\Service\Tax;

use App\Entity\AccountingEntry;
use App\Entity\AccountingPlan;
use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Genere le Fichier des Ecritures Comptables (FEC) conforme a l'article L47 A-1 du LPF.
 *
 * Le FEC est obligatoire pour toute entreprise tenant une comptabilite informatisee.
 * Format : fichier texte avec 18 colonnes separees par des tabulations.
 *
 * Normes respectees :
 * - Article L47 A-1 du Livre des Procedures Fiscales
 * - Arrete du 29 juillet 2013 (format des FEC)
 * - BIC/BNC : nommage SirenFECAAAAMMJJ.txt
 */
class FecExporter
{
    // Les 18 colonnes obligatoires du FEC
    public const COLUMNS = [
        'JournalCode',
        'JournalLib',
        'EcritureNum',
        'EcritureDate',
        'CompteNum',
        'CompteLib',
        'CompAuxNum',
        'CompAuxLib',
        'PieceRef',
        'PieceDate',
        'EcritureLib',
        'Debit',
        'Credit',
        'EcrtureLet',
        'DateLet',
        'ValidDate',
        'Montantdevise',
        'Idevise',
    ];

    // Labels des journaux
    private const JOURNAL_LABELS = [
        'VE' => 'Journal des ventes',
        'AC' => 'Journal des achats',
        'BQ' => 'Journal de banque',
        'OD' => 'Operations diverses',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Genere le contenu du FEC pour un exercice comptable.
     *
     * Chaque ecriture comptable est eclatee en deux lignes FEC :
     * une ligne au debit et une ligne au credit. Les lignes sont
     * triees par date puis par numero d'ecriture.
     *
     * @return string Le contenu du fichier FEC (tab-separated)
     */
    public function export(Company $company, int $year): string
    {
        $periodStart = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $periodEnd = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        // Recuperer les ecritures comptables de l'exercice
        $entries = $this->getEntries($company, $periodStart, $periodEnd);

        // Charger le plan comptable pour les libelles de comptes
        $accountLabels = $this->getAccountLabels($company);

        // Construire le FEC
        $lines = [];

        // En-tete (les 18 colonnes)
        $lines[] = implode("\t", self::COLUMNS);

        $ecritureNum = 1;
        foreach ($entries as $entry) {
            $date = $entry->getEntryDate()->format('Ymd');
            $journalCode = $entry->getJournalCode();
            $journalLib = self::JOURNAL_LABELS[$journalCode] ?? $journalCode;
            $pieceRef = $entry->getPieceReference() ?? '';
            $label = $entry->getLabel();
            $validDate = $entry->isValidated() ? $date : '';
            $numStr = (string) $ecritureNum;

            // Ligne debit
            $debitAccount = $entry->getDebitAccount();
            $debitLabel = $accountLabels[$debitAccount] ?? $debitAccount;
            $compAuxNumDebit = $this->getAuxiliaryAccount($debitAccount);
            $compAuxLibDebit = '' !== $compAuxNumDebit ? ($accountLabels[$compAuxNumDebit] ?? '') : '';

            $lines[] = implode("\t", [
                $journalCode,
                $journalLib,
                $numStr,
                $date,
                $debitAccount,
                $debitLabel,
                $compAuxNumDebit,
                $compAuxLibDebit,
                $pieceRef,
                $date,
                $label,
                $this->formatAmount($entry->getAmount()),
                $this->formatAmount('0.00'),
                '', // EcrtureLet (lettrage)
                '', // DateLet
                $validDate,
                '', // Montantdevise
                '', // Idevise
            ]);

            // Ligne credit
            $creditAccount = $entry->getCreditAccount();
            $creditLabel = $accountLabels[$creditAccount] ?? $creditAccount;
            $compAuxNumCredit = $this->getAuxiliaryAccount($creditAccount);
            $compAuxLibCredit = '' !== $compAuxNumCredit ? ($accountLabels[$compAuxNumCredit] ?? '') : '';

            $lines[] = implode("\t", [
                $journalCode,
                $journalLib,
                $numStr,
                $date,
                $creditAccount,
                $creditLabel,
                $compAuxNumCredit,
                $compAuxLibCredit,
                $pieceRef,
                $date,
                $label,
                $this->formatAmount('0.00'),
                $this->formatAmount($entry->getAmount()),
                '', // EcrtureLet
                '', // DateLet
                $validDate,
                '', // Montantdevise
                '', // Idevise
            ]);

            ++$ecritureNum;
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Genere le nom du fichier FEC conforme.
     *
     * Format : SirenFECAAAAMMJJ.txt
     * La date est celle de la cloture de l'exercice.
     */
    public function generateFileName(Company $company, int $year): string
    {
        return sprintf('%sFEC%d1231.txt', $company->getSiren(), $year);
    }

    /**
     * Retourne le nombre de colonnes du FEC.
     */
    public function getColumnCount(): int
    {
        return \count(self::COLUMNS);
    }

    /**
     * Recupere les ecritures comptables triees par date et journal.
     *
     * @return AccountingEntry[]
     */
    private function getEntries(
        Company $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $qb = $this->em->createQueryBuilder();

        return $qb->select('e')
            ->from(AccountingEntry::class, 'e')
            ->where('e.company = :company')
            ->andWhere('e.entryDate >= :from')
            ->andWhere('e.entryDate <= :to')
            ->setParameter('company', $company)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('e.entryDate', 'ASC')
            ->addOrderBy('e.journalCode', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Charge les libelles des comptes depuis le plan comptable.
     *
     * @return array<string, string>
     */
    private function getAccountLabels(Company $company): array
    {
        $plan = $this->em->getRepository(AccountingPlan::class)->findOneBy(['company' => $company]);

        $labels = [];
        if ($plan instanceof AccountingPlan) {
            foreach ($plan->getAccounts() as $account) {
                $labels[$account->getNumber()] = $account->getLabel();
            }
        }

        return $labels;
    }

    /**
     * Determine le compte auxiliaire pour les comptes clients/fournisseurs.
     *
     * Les comptes 411xxx (clients) et 401xxx (fournisseurs) peuvent
     * avoir un compte auxiliaire pour le suivi par tiers.
     */
    private function getAuxiliaryAccount(string $account): string // phpcs:ignore -- $account reserved for future auxiliary account lookup
    {
        // Les comptes collectifs (411, 401) n'ont pas d'auxiliaire ici
        // car le detail est dans le label de l'ecriture.
        // $account intentionally unused — will be used to resolve auxiliary sub-accounts per client/supplier
        return '';
    }

    /**
     * Formate un montant pour le FEC.
     *
     * Le format attendu est avec virgule comme separateur decimal,
     * conformement aux normes francaises (ex : 1234,56).
     */
    private function formatAmount(string $amount): string
    {
        return str_replace('.', ',', $amount);
    }
}
