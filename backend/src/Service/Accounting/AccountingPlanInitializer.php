<?php

namespace App\Service\Accounting;

use App\Entity\AccountingAccount;
use App\Entity\AccountingPlan;
use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Initialise le plan comptable PCG pour une entreprise.
 *
 * Pre-charge les comptes les plus courants du Plan Comptable General.
 * Les comptes couvrent les classes 1 a 7 necessaires pour une PME.
 */
class AccountingPlanInitializer
{
    /**
     * Comptes PCG par defaut pour une PME / freelance.
     *
     * @var array<int, array{number: string, label: string, type: string}>
     */
    private const DEFAULT_ACCOUNTS = [
        // Classe 1 : Comptes de capitaux
        ['number' => '101000', 'label' => 'Capital social', 'type' => AccountingAccount::TYPE_PASSIF],
        ['number' => '108000', 'label' => 'Compte de l\'exploitant', 'type' => AccountingAccount::TYPE_PASSIF],
        ['number' => '110000', 'label' => 'Report a nouveau', 'type' => AccountingAccount::TYPE_PASSIF],
        ['number' => '120000', 'label' => 'Resultat de l\'exercice (benefice)', 'type' => AccountingAccount::TYPE_PASSIF],
        ['number' => '129000', 'label' => 'Resultat de l\'exercice (perte)', 'type' => AccountingAccount::TYPE_ACTIF],
        ['number' => '164000', 'label' => 'Emprunts bancaires', 'type' => AccountingAccount::TYPE_PASSIF],

        // Classe 2 : Comptes d'immobilisations
        ['number' => '205000', 'label' => 'Logiciels', 'type' => AccountingAccount::TYPE_ACTIF],
        ['number' => '218300', 'label' => 'Materiel informatique', 'type' => AccountingAccount::TYPE_ACTIF],
        ['number' => '218400', 'label' => 'Mobilier de bureau', 'type' => AccountingAccount::TYPE_ACTIF],
        ['number' => '281830', 'label' => 'Amortissement materiel informatique', 'type' => AccountingAccount::TYPE_ACTIF],

        // Classe 4 : Comptes de tiers
        ['number' => '401000', 'label' => 'Fournisseurs', 'type' => AccountingAccount::TYPE_PASSIF],
        ['number' => '411000', 'label' => 'Clients', 'type' => AccountingAccount::TYPE_ACTIF],
        ['number' => '421000', 'label' => 'Personnel - Remunerations dues', 'type' => AccountingAccount::TYPE_PASSIF],
        ['number' => '431000', 'label' => 'Securite sociale', 'type' => AccountingAccount::TYPE_PASSIF],
        ['number' => '445710', 'label' => 'TVA collectee', 'type' => AccountingAccount::TYPE_PASSIF],
        ['number' => '445660', 'label' => 'TVA deductible sur biens et services', 'type' => AccountingAccount::TYPE_ACTIF],
        ['number' => '445800', 'label' => 'TVA a regulariser', 'type' => AccountingAccount::TYPE_PASSIF],
        ['number' => '447100', 'label' => 'Autres impots et taxes', 'type' => AccountingAccount::TYPE_PASSIF],

        // Classe 5 : Comptes financiers
        ['number' => '512000', 'label' => 'Banque', 'type' => AccountingAccount::TYPE_ACTIF],
        ['number' => '530000', 'label' => 'Caisse', 'type' => AccountingAccount::TYPE_ACTIF],

        // Classe 6 : Comptes de charges
        ['number' => '601000', 'label' => 'Achats de matieres premieres', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '604000', 'label' => 'Achats d\'etudes et prestations', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '606300', 'label' => 'Fournitures d\'entretien', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '606400', 'label' => 'Fournitures de bureau', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '611000', 'label' => 'Sous-traitance', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '613200', 'label' => 'Loyers', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '615000', 'label' => 'Entretien et reparations', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '616000', 'label' => 'Assurances', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '622600', 'label' => 'Honoraires', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '623000', 'label' => 'Publicite', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '625100', 'label' => 'Deplacements', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '625700', 'label' => 'Receptions', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '626000', 'label' => 'Frais postaux et telecommunications', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '627000', 'label' => 'Services bancaires', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '641000', 'label' => 'Remunerations du personnel', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '645000', 'label' => 'Charges sociales', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '646000', 'label' => 'Cotisations sociales exploitant', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '651000', 'label' => 'Redevances et licences', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '661000', 'label' => 'Interets des emprunts', 'type' => AccountingAccount::TYPE_CHARGE],
        ['number' => '681000', 'label' => 'Dotations aux amortissements', 'type' => AccountingAccount::TYPE_CHARGE],

        // Classe 7 : Comptes de produits
        ['number' => '706000', 'label' => 'Prestations de services', 'type' => AccountingAccount::TYPE_PRODUIT],
        ['number' => '707000', 'label' => 'Ventes de marchandises', 'type' => AccountingAccount::TYPE_PRODUIT],
        ['number' => '708000', 'label' => 'Produits des activites annexes', 'type' => AccountingAccount::TYPE_PRODUIT],
        ['number' => '761000', 'label' => 'Produits de participations', 'type' => AccountingAccount::TYPE_PRODUIT],
        ['number' => '764000', 'label' => 'Revenus des valeurs mobilieres', 'type' => AccountingAccount::TYPE_PRODUIT],
        ['number' => '791000', 'label' => 'Transferts de charges', 'type' => AccountingAccount::TYPE_PRODUIT],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Cree un plan comptable PCG par defaut pour une entreprise.
     */
    public function initialize(Company $company): AccountingPlan
    {
        $plan = new AccountingPlan();
        $plan->setCompany($company);

        foreach (self::DEFAULT_ACCOUNTS as $accountData) {
            $account = new AccountingAccount();
            $account->setNumber($accountData['number']);
            $account->setLabel($accountData['label']);
            $account->setType($accountData['type']);
            $account->setIsDefault(true);

            $plan->addAccount($account);
        }

        $this->em->persist($plan);
        $this->em->flush();

        return $plan;
    }

    /**
     * Retourne la definition des comptes par defaut.
     *
     * @return array<int, array{number: string, label: string, type: string}>
     */
    public static function getDefaultAccounts(): array
    {
        return self::DEFAULT_ACCOUNTS;
    }
}
