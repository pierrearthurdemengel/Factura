<?php

namespace App\Message;

use App\Entity\Invoice;
use App\Entity\ReminderEvent;
use App\Entity\ReminderTemplate;
use App\Service\Reminder\FormalNoticePdfGenerator;
use App\Service\Reminder\ReminderTemplateProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/**
 * Handler Messenger : envoie un email de relance pour une facture.
 *
 * Recupere le template adapte, interpole les variables, envoie l'email
 * et enregistre un ReminderEvent dans l'historique.
 */
#[AsMessageHandler]
class SendReminderHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly ReminderTemplateProvider $templateProvider,
        private readonly FormalNoticePdfGenerator $formalNoticePdfGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendReminderMessage $message): void
    {
        $invoice = $this->em->getRepository(Invoice::class)->find($message->getInvoiceId());

        if (!$this->isEligibleForReminder($invoice, $message)) {
            return;
        }

        \assert(null !== $invoice);
        $buyer = $invoice->getBuyer();
        /** @var string $recipientEmail */
        $recipientEmail = $buyer->getEmail();
        /** @var \App\Entity\Company $seller */
        $seller = $invoice->getSeller();
        $reminderType = $message->getReminderType();

        // Recuperer le template (personnalise ou par defaut)
        $template = $this->templateProvider->getTemplate($seller, $reminderType);

        // Variables d'interpolation
        $variables = [
            'client' => $buyer->getName(),
            'montant' => $invoice->getTotalIncludingTax() . ' EUR',
            'echeance' => null !== $invoice->getDueDate() ? $invoice->getDueDate()->format('d/m/Y') : 'N/A',
            'numero' => $invoice->getNumber() ?? 'N/A',
            'entreprise' => $seller->getName(),
        ];

        $subject = $template->renderSubject($variables);
        $body = $template->render($variables);

        try {
            $email = (new Email())
                ->from(sprintf('noreply@%s.factura.fr', $this->slugify($seller->getName())))
                ->to($recipientEmail)
                ->subject($subject)
                ->text($body);

            // Joindre le PDF de mise en demeure si applicable
            $formalNoticePath = null;
            if (ReminderTemplate::TYPE_FORMAL_NOTICE === $reminderType) {
                $pdfContent = $this->formalNoticePdfGenerator->generate($invoice);
                $email->attach($pdfContent, 'mise-en-demeure.pdf', 'application/pdf');
            }

            $this->mailer->send($email);

            // Enregistrer l'evenement de relance
            $event = new ReminderEvent(
                $invoice,
                $reminderType,
                $recipientEmail,
                $subject,
                'SENT',
                null,
                $formalNoticePath,
            );
            $this->em->persist($event);
            $this->em->flush();

            $this->logger->info('Relance envoyee avec succes.', [
                'invoiceNumber' => $invoice->getNumber(),
                'type' => $reminderType,
                'recipient' => $recipientEmail,
            ]);
        } catch (\Throwable $e) {
            // Enregistrer l'echec
            $event = new ReminderEvent(
                $invoice,
                $reminderType,
                $recipientEmail,
                $subject,
                'FAILED',
                $e->getMessage(),
            );
            $this->em->persist($event);
            $this->em->flush();

            $this->logger->error('Echec de l\'envoi de la relance.', [
                'invoiceNumber' => $invoice->getNumber(),
                'type' => $reminderType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Verifie que la facture est eligible a une relance.
     */
    private function isEligibleForReminder(?Invoice $invoice, SendReminderMessage $message): bool
    {
        if (null === $invoice) {
            $this->logger->error('Facture introuvable pour la relance.', [
                'invoiceId' => $message->getInvoiceId(),
            ]);

            return false;
        }

        return $this->hasValidReminderPrerequisites($invoice, $message);
    }

    /**
     * Verifie les prerequis d'envoi de relance (statut, email destinataire, vendeur).
     */
    private function hasValidReminderPrerequisites(Invoice $invoice, SendReminderMessage $message): bool
    {
        if ('SENT' !== $invoice->getStatus()) {
            $this->logger->info('Relance ignoree : facture non en statut SENT.', [
                'invoiceNumber' => $invoice->getNumber(),
                'status' => $invoice->getStatus(),
            ]);

            return false;
        }

        $recipientEmail = $invoice->getBuyer()->getEmail();
        if ((null === $recipientEmail || '' === $recipientEmail) || null === $invoice->getSeller()) {
            $this->logMissingPrerequisite($invoice, $message, $recipientEmail);

            return false;
        }

        return true;
    }

    /**
     * Journalise le prerequis manquant (email ou vendeur).
     */
    private function logMissingPrerequisite(Invoice $invoice, SendReminderMessage $message, ?string $recipientEmail): void
    {
        if (null === $recipientEmail || '' === $recipientEmail) {
            $this->logger->warning('Relance impossible : pas d\'email pour le client.', [
                'invoiceNumber' => $invoice->getNumber(),
                'clientName' => $invoice->getBuyer()->getName(),
            ]);
        } else {
            $this->logger->error('Relance impossible : facture sans vendeur.', [
                'invoiceId' => $message->getInvoiceId(),
            ]);
        }
    }

    /**
     * Genere un slug simple a partir du nom de l'entreprise.
     */
    private function slugify(string $text): string
    {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (false === $slug) {
            $slug = $text;
        }
        $slug = strtolower($slug);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }
}
