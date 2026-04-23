<?php

namespace App\Service\Reminder;

use App\Entity\Invoice;
use App\Entity\ReminderConfig;
use App\Entity\ReminderEvent;
use App\Entity\ReminderTemplate;
use App\Message\SendReminderMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Planificateur de relances automatiques.
 *
 * Parcourt toutes les factures en statut SENT avec une echeance depassee
 * (ou proche), determine le type de relance a envoyer, et dispatche
 * un SendReminderMessage pour chaque relance necessaire.
 */
class ReminderScheduler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Detecte les factures necessitant une relance et dispatche les messages.
     *
     * @return int Nombre de relances dispatchees
     */
    public function schedule(\DateTimeImmutable $today): int
    {
        $dispatched = 0;

        // Recuperer toutes les configs de relance actives
        $configs = $this->em->getRepository(ReminderConfig::class)->findBy(['enabled' => true]);

        foreach ($configs as $config) {
            $company = $config->getCompany();

            // Recuperer les factures SENT de cette entreprise avec une echeance
            $invoices = $this->em->getRepository(Invoice::class)->findBy([
                'seller' => $company,
                'status' => 'SENT',
            ]);

            foreach ($invoices as $invoice) {
                $dueDate = $invoice->getDueDate();
                if (null === $dueDate) {
                    continue;
                }

                $reminderType = $this->determineReminderType($today, $dueDate, $config);
                if (null === $reminderType) {
                    continue;
                }

                // Verifier qu'on n'a pas deja envoye ce type de relance pour cette facture
                if ($this->hasAlreadySentReminder($invoice, $reminderType)) {
                    continue;
                }

                // Dispatcher la relance
                $this->messageBus->dispatch(new SendReminderMessage(
                    $invoice->getId()?->toRfc4122() ?? '',
                    $reminderType,
                ));

                ++$dispatched;

                $this->logger->info('Relance planifiee.', [
                    'invoiceNumber' => $invoice->getNumber(),
                    'type' => $reminderType,
                    'dueDate' => $dueDate->format('Y-m-d'),
                ]);
            }
        }

        return $dispatched;
    }

    /**
     * Determine le type de relance a envoyer en fonction de la date du jour
     * et de la date d'echeance.
     *
     * Retourne le type de relance le plus pertinent (le plus avance) :
     * - J-3 (ou configurable) : rappel avant echeance
     * - J+1 : premiere relance
     * - J+7 : deuxieme relance
     * - J+30 : mise en demeure
     */
    public function determineReminderType(
        \DateTimeImmutable $today,
        \DateTimeImmutable $dueDate,
        ReminderConfig $config,
    ): ?string {
        $diff = $today->diff($dueDate);
        $daysFromDue = (int) $diff->format('%r%a');
        // daysFromDue est negatif si la date d'echeance est passee (today > dueDate)
        $daysOverdue = -$daysFromDue;

        // Echeance depassee : determiner le niveau de relance le plus avance
        return match (true) {
            $daysOverdue >= $config->getDaysFormalNotice() && $config->isFormalNoticeEnabled() => ReminderTemplate::TYPE_FORMAL_NOTICE,
            $daysOverdue >= $config->getDaysSecondReminder() => ReminderTemplate::TYPE_SECOND_REMINDER,
            $daysOverdue >= $config->getDaysFirstReminder() => ReminderTemplate::TYPE_FIRST_REMINDER,
            $daysFromDue > 0 && $daysFromDue <= $config->getDaysBefore() => ReminderTemplate::TYPE_BEFORE_DUE,
            default => null,
        };
    }

    /**
     * Verifie si une relance de ce type a deja ete envoyee pour cette facture.
     */
    private function hasAlreadySentReminder(Invoice $invoice, string $reminderType): bool
    {
        $existing = $this->em->getRepository(ReminderEvent::class)->findOneBy([
            'invoice' => $invoice,
            'reminderType' => $reminderType,
            'status' => 'SENT',
        ]);

        return null !== $existing;
    }
}
