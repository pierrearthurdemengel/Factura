<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\Product;
use App\Entity\Quote;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Filtre automatiquement toutes les requetes de collection par l'entreprise
 * de l'utilisateur connecte. Garantit l'isolation multi-tenant :
 * aucune requete ne peut retourner les donnees d'une autre entreprise.
 */
class InvoiceOwnerExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $company = $user->getCompany();
        if (null === $company) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (Invoice::class === $resourceClass || Quote::class === $resourceClass) {
            $queryBuilder
                ->andWhere(sprintf('%s.seller = :company', $rootAlias))
                ->setParameter('company', $company);
        } elseif (Client::class === $resourceClass) {
            $queryBuilder
                ->andWhere(sprintf('%s.company = :company', $rootAlias))
                ->setParameter('company', $company);
        } elseif (Product::class === $resourceClass) {
            $queryBuilder
                ->andWhere(sprintf('%s.company = :company', $rootAlias))
                ->setParameter('company', $company);
        }
    }
}
