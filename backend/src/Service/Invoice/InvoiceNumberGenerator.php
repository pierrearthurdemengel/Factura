<?php

namespace App\Service\Invoice;

use App\Entity\Company;
use App\Exception\InvoiceNumberGenerationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Genere un numero de facture sequentiel unique pour une entreprise.
 *
 * Le format est FA-AAAA-NNNN ou AAAA est l'annee en cours et NNNN
 * est un entier strictement croissant, reinitialise a 1 chaque annee.
 * Un verrou BDD (SELECT FOR UPDATE) garantit l'unicite en acces concurrent.
 *
 * @throws InvoiceNumberGenerationException Si le verrou ne peut pas etre obtenu
 */
class InvoiceNumberGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function generate(Company $company): string
    {
        $connection = $this->em->getConnection();

        try {
            $connection->beginTransaction();

            // Verrou pessimiste sur la ligne de l'entreprise
            $sql = 'SELECT last_invoice_number, last_invoice_year FROM companies WHERE id = :id FOR UPDATE';
            $result = $connection->executeQuery($sql, ['id' => $company->getId()->toRfc4122()]);
            $row = $result->fetchAssociative();

            if (false === $row) {
                throw new InvoiceNumberGenerationException('Entreprise introuvable.');
            }

            $currentYear = (int) date('Y');
            $lastYear = $row['last_invoice_year'] ? (int) $row['last_invoice_year'] : null;
            $lastNumber = $row['last_invoice_number'] ? (int) $row['last_invoice_number'] : 0;

            // Reinitialisation annuelle du compteur
            if ($lastYear !== $currentYear) {
                $nextNumber = 1;
            } else {
                $nextNumber = $lastNumber + 1;
            }

            // Mise a jour du compteur
            $connection->executeStatement(
                'UPDATE companies SET last_invoice_number = :number, last_invoice_year = :year WHERE id = :id',
                [
                    'number' => $nextNumber,
                    'year' => $currentYear,
                    'id' => $company->getId()->toRfc4122(),
                ],
            );

            $connection->commit();

            return sprintf('FA-%d-%04d', $currentYear, $nextNumber);
        } catch (InvoiceNumberGenerationException $e) {
            $connection->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw new InvoiceNumberGenerationException('Erreur lors de la generation du numero : ' . $e->getMessage());
        }
    }
}
