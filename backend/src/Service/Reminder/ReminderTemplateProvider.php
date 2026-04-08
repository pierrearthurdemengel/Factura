<?php

namespace App\Service\Reminder;

use App\Entity\Company;
use App\Entity\ReminderTemplate;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fournit le template de relance adapte au type demande.
 *
 * Retourne le template personnalise de l'entreprise s'il existe,
 * sinon un template par defaut en francais.
 */
class ReminderTemplateProvider
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Recupere le template de relance pour une entreprise et un type donne.
     *
     * Si aucun template personnalise n'existe, retourne un template par defaut.
     */
    public function getTemplate(Company $company, string $type): ReminderTemplate
    {
        // Chercher un template personnalise pour cette entreprise
        $template = $this->em->getRepository(ReminderTemplate::class)->findOneBy([
            'company' => $company,
            'type' => $type,
        ]);

        if (null !== $template) {
            return $template;
        }

        // Retourner un template par defaut
        return $this->getDefaultTemplate($type);
    }

    /**
     * Genere un template par defaut selon le type de relance.
     *
     * Le ton est progressivement plus ferme : amical pour le rappel,
     * ferme pour la premiere relance, insistant pour la deuxieme,
     * formel pour la mise en demeure.
     */
    private function getDefaultTemplate(string $type): ReminderTemplate
    {
        $template = new ReminderTemplate();

        switch ($type) {
            case ReminderTemplate::TYPE_BEFORE_DUE:
                $template->setSubject('Rappel : facture {numero} a echeance le {echeance}');
                $template->setBody(
                    "Bonjour,\n\n"
                    . "Nous vous rappelons que la facture {numero} d'un montant de {montant} "
                    . "arrive a echeance le {echeance}.\n\n"
                    . "Si le reglement est deja en cours, veuillez ne pas tenir compte de ce message.\n\n"
                    . "Cordialement,\n{entreprise}"
                );
                break;

            case ReminderTemplate::TYPE_FIRST_REMINDER:
                $template->setSubject('Relance : facture {numero} echue');
                $template->setBody(
                    "Bonjour,\n\n"
                    . "Sauf erreur de notre part, nous n'avons pas recu le reglement de la facture "
                    . "{numero} d'un montant de {montant}, echue le {echeance}.\n\n"
                    . "Nous vous remercions de bien vouloir proceder au reglement dans les meilleurs delais.\n\n"
                    . "Cordialement,\n{entreprise}"
                );
                break;

            case ReminderTemplate::TYPE_SECOND_REMINDER:
                $template->setSubject('2e relance : facture {numero} impayee');
                $template->setBody(
                    "Bonjour,\n\n"
                    . "Malgre notre precedent rappel, la facture {numero} d'un montant de {montant} "
                    . "reste impayee a ce jour. L'echeance etait fixee au {echeance}.\n\n"
                    . 'Nous vous prions de regulariser cette situation dans les plus brefs delais. '
                    . "A defaut, nous serons contraints d'engager des procedures de recouvrement.\n\n"
                    . "Cordialement,\n{entreprise}"
                );
                break;

            case ReminderTemplate::TYPE_FORMAL_NOTICE:
                $template->setSubject('Mise en demeure : facture {numero}');
                $template->setBody(
                    "Bonjour,\n\n"
                    . 'Par la presente, nous vous mettons en demeure de proceder au paiement '
                    . "de la facture {numero} d'un montant de {montant}, echue depuis le {echeance}.\n\n"
                    . 'Conformement aux articles L.441-10 et L.441-6 du Code de commerce, '
                    . 'des penalites de retard et une indemnite forfaitaire de recouvrement de 40 EUR '
                    . "sont exigibles de plein droit.\n\n"
                    . "Vous trouverez ci-joint le courrier de mise en demeure.\n\n"
                    . "Sans reglement sous 8 jours, nous transmettrons le dossier a notre service contentieux.\n\n"
                    . "Cordialement,\n{entreprise}"
                );
                break;

            default:
                $template->setSubject('Relance : facture {numero}');
                $template->setBody(
                    "Bonjour,\n\n"
                    . "Nous vous contactons au sujet de la facture {numero} d'un montant de {montant}.\n\n"
                    . "Cordialement,\n{entreprise}"
                );
        }

        $template->setType($type);

        return $template;
    }
}
