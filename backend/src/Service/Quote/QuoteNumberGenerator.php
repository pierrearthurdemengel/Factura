<?php

namespace App\Service\Quote;

use App\Entity\Company;
use App\Exception\QuoteNumberGenerationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Genere un numero de devis sequentiel unique pour une entreprise.
 *
 * Le format est DV-AAAA-NNNN ou AAAA est l'annee en cours et NNNN
 * est un entier strictement croissant, reinitialise a 1 chaque annee.
 * Un verrou BDD (SELECT FOR UPDATE) garantit l'unicite en acces concurrent.
 *
 * @throws QuoteNumberGenerationException Si le verrou ne peut pas etre obtenu
 */
class QuoteNumberGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function generate(Company $company): string
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            // Verrou pessimiste sur la ligne company
            $sql = 'SELECT last_quote_number, last_quote_year FROM companies WHERE id = :id FOR UPDATE';
            $row = $connection->fetchAssociative($sql, ['id' => $company->getId()?->toRfc4122()]);

            if (false === $row) {
                throw new QuoteNumberGenerationException('Entreprise introuvable.');
            }

            $currentYear = (int) date('Y');
            $lastYear = $row['last_quote_year'] ? (int) $row['last_quote_year'] : 0;
            $lastNumber = $row['last_quote_number'] ? (int) $row['last_quote_number'] : 0;

            // Reinitialisation annuelle
            if ($currentYear !== $lastYear) {
                $newNumber = 1;
            } else {
                $newNumber = $lastNumber + 1;
            }

            // Mise a jour du compteur
            $connection->executeStatement(
                'UPDATE companies SET last_quote_number = :number, last_quote_year = :year WHERE id = :id',
                [
                    'number' => $newNumber,
                    'year' => $currentYear,
                    'id' => $company->getId()?->toRfc4122(),
                ],
            );

            $connection->commit();

            // Synchroniser l'entite en memoire
            $company->setLastQuoteNumber($newNumber);
            $company->setLastQuoteYear($currentYear);

            return sprintf('DV-%d-%04d', $currentYear, $newNumber);
        } catch (QuoteNumberGenerationException $e) {
            $connection->rollBack();

            throw $e;
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw new QuoteNumberGenerationException('Impossible de generer le numero de devis.', 0, $e);
        }
    }
}
