<?php

namespace App\Service\Mcp;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\User;
use App\Message\TransmitInvoiceToPdpMessage;
use App\Service\Invoice\AuditTrailRecorder;
use App\Service\Invoice\InvoiceNumberGenerator;
use App\Service\Invoice\InvoiceStateMachine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Executeur de tools MCP.
 * Recoit un nom de tool et ses arguments, execute l'action correspondante
 * en utilisant les services metier existants, et retourne le resultat.
 *
 * @phpstan-type ToolResult array{content: list<array{type: string, text: string}>, isError?: bool}
 */
class McpServer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceStateMachine $stateMachine,
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly AuditTrailRecorder $auditTrail,
        private readonly MessageBusInterface $bus,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Execute un tool MCP et retourne le resultat formate.
     *
     * @param array<string, mixed> $arguments
     *
     * @return ToolResult
     */
    public function executeTool(string $toolName, array $arguments, User $user): array
    {
        $company = $user->getCompany();
        if (null === $company) {
            return $this->errorResult('Aucune entreprise configuree pour ce compte.');
        }

        return match ($toolName) {
            'list_clients' => $this->listClients($company, $arguments),
            'create_client' => $this->createClient($company, $arguments),
            'list_invoices' => $this->listInvoices($company, $arguments),
            'get_invoice' => $this->getInvoice($company, $arguments),
            'create_invoice' => $this->createInvoice($company, $arguments),
            'send_invoice' => $this->sendInvoice($company, $arguments),
            'mark_invoice_paid' => $this->markInvoicePaid($company, $arguments),
            'cancel_invoice' => $this->cancelInvoice($company, $arguments),
            'get_invoice_pdf_url' => $this->getInvoicePdfUrl($company, $arguments),
            'get_dashboard_stats' => $this->getDashboardStats($company),
            default => $this->errorResult("Tool inconnu : {$toolName}"),
        };
    }

    /**
     * Liste les clients de l'entreprise, avec filtre optionnel par nom.
     *
     * @param array<string, mixed> $arguments
     *
     * @return ToolResult
     */
    private function listClients(Company $company, array $arguments): array
    {
        $qb = $this->em->getRepository(Client::class)->createQueryBuilder('c')
            ->where('c.company = :company')
            ->setParameter('company', $company)
            ->orderBy('c.name', 'ASC');

        $search = $arguments['search'] ?? null;
        if (is_string($search) && '' !== $search) {
            $qb->andWhere('LOWER(c.name) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        /** @var list<Client> $clients */
        $clients = $qb->getQuery()->getResult();

        $result = array_map(fn (Client $c) => [
            'id' => $c->getId()?->toRfc4122(),
            'name' => $c->getName(),
            'siren' => $c->getSiren(),
            'address' => sprintf('%s, %s %s', $c->getAddressLine1(), $c->getPostalCode(), $c->getCity()),
            'countryCode' => $c->getCountryCode(),
        ], $clients);

        return $this->textResult(json_encode($result, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Cree un nouveau client pour l'entreprise.
     *
     * @param array<string, mixed> $arguments
     *
     * @return ToolResult
     */
    private function createClient(Company $company, array $arguments): array
    {
        $name = $arguments['name'] ?? null;
        $addressLine1 = $arguments['addressLine1'] ?? null;
        $postalCode = $arguments['postalCode'] ?? null;
        $city = $arguments['city'] ?? null;

        if (!is_string($name) || '' === $name) {
            return $this->errorResult('Le nom du client est obligatoire.');
        }
        if (!is_string($addressLine1) || '' === $addressLine1) {
            return $this->errorResult("L'adresse est obligatoire.");
        }
        if (!is_string($postalCode) || '' === $postalCode) {
            return $this->errorResult('Le code postal est obligatoire.');
        }
        if (!is_string($city) || '' === $city) {
            return $this->errorResult('La ville est obligatoire.');
        }

        $client = new Client();
        $client->setCompany($company);
        $client->setName($name);
        $client->setAddressLine1($addressLine1);
        $client->setPostalCode($postalCode);
        $client->setCity($city);

        $siren = $arguments['siren'] ?? null;
        if (is_string($siren) && '' !== $siren) {
            $client->setSiren($siren);
        }

        $countryCode = $arguments['countryCode'] ?? null;
        if (is_string($countryCode) && '' !== $countryCode) {
            $client->setCountryCode($countryCode);
        }

        $this->em->persist($client);
        $this->em->flush();

        return $this->textResult(json_encode([
            'id' => $client->getId()?->toRfc4122(),
            'name' => $client->getName(),
            'message' => "Client \"{$client->getName()}\" cree avec succes.",
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Liste les factures de l'entreprise avec filtre optionnel par statut.
     *
     * @param array<string, mixed> $arguments
     *
     * @return ToolResult
     */
    private function listInvoices(Company $company, array $arguments): array
    {
        $qb = $this->em->getRepository(Invoice::class)->createQueryBuilder('i')
            ->where('i.seller = :company')
            ->setParameter('company', $company)
            ->orderBy('i.issueDate', 'DESC');

        $status = $arguments['status'] ?? null;
        if (is_string($status) && '' !== $status) {
            $qb->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        $limit = $arguments['limit'] ?? 20;
        $qb->setMaxResults(is_int($limit) ? min($limit, 100) : 20);

        /** @var list<Invoice> $invoices */
        $invoices = $qb->getQuery()->getResult();

        $result = array_map(fn (Invoice $inv) => [
            'id' => $inv->getId()?->toRfc4122(),
            'number' => $inv->getNumber(),
            'status' => $inv->getStatus(),
            'buyer' => $inv->getBuyer()->getName(),
            'issueDate' => $inv->getIssueDate()->format('Y-m-d'),
            'totalExcludingTax' => $inv->getTotalExcludingTax(),
            'totalIncludingTax' => $inv->getTotalIncludingTax(),
            'currency' => $inv->getCurrency(),
        ], $invoices);

        return $this->textResult(json_encode($result, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Recupere le detail d'une facture par ID ou numero.
     *
     * @param array<string, mixed> $arguments
     *
     * @return ToolResult
     */
    private function getInvoice(Company $company, array $arguments): array
    {
        $invoice = $this->resolveInvoice($company, $arguments);
        if (null === $invoice) {
            return $this->errorResult('Facture introuvable.');
        }

        $lines = [];
        foreach ($invoice->getLines() as $line) {
            $lines[] = [
                'description' => $line->getDescription(),
                'quantity' => $line->getQuantity(),
                'unit' => $line->getUnit(),
                'unitPriceExcludingTax' => $line->getUnitPriceExcludingTax(),
                'vatRate' => $line->getVatRate(),
                'lineAmount' => $line->getLineAmount(),
                'vatAmount' => $line->getVatAmount(),
            ];
        }

        $result = [
            'id' => $invoice->getId()?->toRfc4122(),
            'number' => $invoice->getNumber(),
            'status' => $invoice->getStatus(),
            'buyer' => $invoice->getBuyer()->getName(),
            'issueDate' => $invoice->getIssueDate()->format('Y-m-d'),
            'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
            'paymentTerms' => $invoice->getPaymentTerms(),
            'totalExcludingTax' => $invoice->getTotalExcludingTax(),
            'totalTax' => $invoice->getTotalTax(),
            'totalIncludingTax' => $invoice->getTotalIncludingTax(),
            'currency' => $invoice->getCurrency(),
            'lines' => $lines,
        ];

        return $this->textResult(json_encode($result, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Cree une facture avec resolution automatique du client par nom.
     *
     * @param array<string, mixed> $arguments
     *
     * @return ToolResult
     */
    private function createInvoice(Company $company, array $arguments): array
    {
        // Resolution du client par nom
        $clientName = $arguments['clientName'] ?? null;
        if (!is_string($clientName) || '' === $clientName) {
            return $this->errorResult('Le nom du client est obligatoire.');
        }

        $client = $this->em->getRepository(Client::class)->findOneBy([
            'company' => $company,
            'name' => $clientName,
        ]);

        if (null === $client) {
            return $this->errorResult("Client \"{$clientName}\" introuvable. Creez-le d'abord avec create_client.");
        }

        // Validation des lignes
        $linesData = $arguments['lines'] ?? null;
        if (!is_array($linesData) || [] === $linesData) {
            return $this->errorResult('Au moins une ligne est requise.');
        }

        $invoice = new Invoice();
        $invoice->setSeller($company);
        $invoice->setBuyer($client);

        // Date d'emission
        $issueDate = $arguments['issueDate'] ?? null;
        if (is_string($issueDate) && '' !== $issueDate) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $issueDate);
            if (false !== $parsed) {
                $invoice->setIssueDate($parsed);
            }
        }

        // Date d'echeance
        $dueDate = $arguments['dueDate'] ?? null;
        if (is_string($dueDate) && '' !== $dueDate) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $dueDate);
            if (false !== $parsed) {
                $invoice->setDueDate($parsed);
            }
        }

        // Conditions de paiement
        $paymentTerms = $arguments['paymentTerms'] ?? null;
        if (is_string($paymentTerms) && '' !== $paymentTerms) {
            $invoice->setPaymentTerms($paymentTerms);
        }

        // Ajout des lignes
        $position = 1;
        foreach ($linesData as $lineData) {
            if (!is_array($lineData)) {
                continue;
            }

            $description = $lineData['description'] ?? null;
            $quantity = $lineData['quantity'] ?? null;
            $unitPrice = $lineData['unitPriceExcludingTax'] ?? null;

            if (!is_string($description) || '' === $description) {
                return $this->errorResult("Ligne {$position} : description obligatoire.");
            }
            if (!is_string($quantity) || '' === $quantity) {
                return $this->errorResult("Ligne {$position} : quantite obligatoire.");
            }
            if (!is_string($unitPrice) || '' === $unitPrice) {
                return $this->errorResult("Ligne {$position} : prix unitaire HT obligatoire.");
            }

            $line = new InvoiceLine();
            $line->setPosition($position);
            $line->setDescription($description);
            $line->setQuantity($quantity);
            $line->setUnitPriceExcludingTax($unitPrice);

            $unit = $lineData['unit'] ?? null;
            if (is_string($unit) && '' !== $unit) {
                $line->setUnit($unit);
            }

            $vatRate = $lineData['vatRate'] ?? null;
            if (is_string($vatRate) && '' !== $vatRate) {
                $line->setVatRate($vatRate);
            }

            $line->computeAmounts();
            $invoice->addLine($line);
            ++$position;
        }

        $invoice->computeTotals();

        $this->em->persist($invoice);
        $this->em->flush();

        return $this->textResult(json_encode([
            'id' => $invoice->getId()?->toRfc4122(),
            'status' => $invoice->getStatus(),
            'buyer' => $client->getName(),
            'totalExcludingTax' => $invoice->getTotalExcludingTax(),
            'totalIncludingTax' => $invoice->getTotalIncludingTax(),
            'linesCount' => $invoice->getLines()->count(),
            'message' => 'Facture creee en brouillon. Utilisez send_invoice pour l\'emettre.',
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Emet une facture : genere le numero, applique la transition, lance la transmission PDP.
     *
     * @param array<string, mixed> $arguments
     *
     * @return ToolResult
     */
    private function sendInvoice(Company $company, array $arguments): array
    {
        $invoice = $this->resolveInvoice($company, $arguments);
        if (null === $invoice) {
            return $this->errorResult('Facture introuvable.');
        }

        if (!$this->stateMachine->can($invoice, 'send')) {
            return $this->errorResult("Impossible d'emettre cette facture (statut actuel : {$invoice->getStatus()}).");
        }

        // Generer le numero de facture
        if (null === $invoice->getNumber()) {
            $number = $this->numberGenerator->generate($company);
            $invoice->setNumber($number);
        }

        $invoice->computeTotals();
        $oldStatus = $invoice->getStatus();

        $this->stateMachine->apply($invoice, 'send');
        $this->em->flush();

        $this->auditTrail->recordTransition($invoice, $oldStatus, $invoice->getStatus());

        // Transmission asynchrone a la PDP
        $invoiceId = $invoice->getId();
        \assert(null !== $invoiceId);
        $this->bus->dispatch(new TransmitInvoiceToPdpMessage($invoiceId->toRfc4122()));

        return $this->textResult(json_encode([
            'id' => $invoiceId->toRfc4122(),
            'number' => $invoice->getNumber(),
            'status' => $invoice->getStatus(),
            'message' => "Facture {$invoice->getNumber()} emise et transmise a la PDP.",
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Marque une facture comme payee.
     *
     * @param array<string, mixed> $arguments
     *
     * @return ToolResult
     */
    private function markInvoicePaid(Company $company, array $arguments): array
    {
        $invoice = $this->resolveInvoice($company, $arguments);
        if (null === $invoice) {
            return $this->errorResult('Facture introuvable.');
        }

        if (!$this->stateMachine->can($invoice, 'pay')) {
            return $this->errorResult("Impossible de marquer cette facture comme payee (statut actuel : {$invoice->getStatus()}).");
        }

        $oldStatus = $invoice->getStatus();
        $this->stateMachine->apply($invoice, 'pay');
        $this->em->flush();

        $this->auditTrail->recordTransition($invoice, $oldStatus, $invoice->getStatus());

        return $this->textResult(json_encode([
            'id' => $invoice->getId()?->toRfc4122(),
            'number' => $invoice->getNumber(),
            'status' => $invoice->getStatus(),
            'message' => "Facture {$invoice->getNumber()} marquee comme payee.",
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Annule une facture.
     *
     * @param array<string, mixed> $arguments
     *
     * @return ToolResult
     */
    private function cancelInvoice(Company $company, array $arguments): array
    {
        $invoice = $this->resolveInvoice($company, $arguments);
        if (null === $invoice) {
            return $this->errorResult('Facture introuvable.');
        }

        if (!$this->stateMachine->can($invoice, 'cancel')) {
            return $this->errorResult("Impossible d'annuler cette facture (statut actuel : {$invoice->getStatus()}).");
        }

        $oldStatus = $invoice->getStatus();
        $this->stateMachine->apply($invoice, 'cancel');
        $this->em->flush();

        $this->auditTrail->recordTransition($invoice, $oldStatus, $invoice->getStatus());

        return $this->textResult(json_encode([
            'id' => $invoice->getId()?->toRfc4122(),
            'number' => $invoice->getNumber(),
            'status' => $invoice->getStatus(),
            'message' => 'Facture annulee.',
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Retourne l'URL de telechargement du PDF Factur-X d'une facture.
     *
     * @param array<string, mixed> $arguments
     *
     * @return ToolResult
     */
    private function getInvoicePdfUrl(Company $company, array $arguments): array
    {
        $invoice = $this->resolveInvoice($company, $arguments);
        if (null === $invoice) {
            return $this->errorResult('Facture introuvable.');
        }

        $invoiceId = $invoice->getId();
        \assert(null !== $invoiceId);

        $url = $this->urlGenerator->generate('api_invoice_download_pdf', [
            'id' => $invoiceId->toRfc4122(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->textResult(json_encode([
            'id' => $invoiceId->toRfc4122(),
            'number' => $invoice->getNumber(),
            'pdfUrl' => $url,
            'message' => "URL de telechargement du PDF pour {$invoice->getNumber()}.",
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Retourne les statistiques de facturation du mois en cours.
     *
     * @return ToolResult
     */
    private function getDashboardStats(Company $company): array
    {
        $companyId = $company->getId();
        \assert(null !== $companyId);

        $conn = $this->em->getConnection();

        $sql = <<<'SQL'
            SELECT
                COUNT(*) as total_invoices,
                COALESCE(SUM(total_excluding_tax), 0) as total_revenue_ht,
                SUM(CASE WHEN status IN ('SENT', 'ACKNOWLEDGED') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as paid,
                COALESCE(SUM(CASE WHEN status = 'PAID' THEN total_excluding_tax ELSE 0 END), 0) as paid_amount_ht
            FROM invoices
            WHERE seller_id = :seller_id
              AND EXTRACT(YEAR FROM issue_date) = :year
              AND EXTRACT(MONTH FROM issue_date) = :month
            SQL;

        $now = new \DateTimeImmutable();
        $result = $conn->executeQuery($sql, [
            'seller_id' => $companyId->toRfc4122(),
            'year' => (int) $now->format('Y'),
            'month' => (int) $now->format('n'),
        ])->fetchAssociative();

        if (false === $result) {
            $result = [
                'total_invoices' => 0,
                'total_revenue_ht' => '0.00',
                'pending' => 0,
                'paid' => 0,
                'paid_amount_ht' => '0.00',
            ];
        }

        return $this->textResult(json_encode([
            'month' => $now->format('Y-m'),
            'totalInvoices' => (int) $result['total_invoices'],
            'totalRevenueExcludingTax' => $result['total_revenue_ht'],
            'pendingInvoices' => (int) $result['pending'],
            'paidInvoices' => (int) $result['paid'],
            'paidAmountExcludingTax' => $result['paid_amount_ht'],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Resout une facture par ID ou numero, en verifiant qu'elle appartient a l'entreprise.
     *
     * @param array<string, mixed> $arguments
     */
    private function resolveInvoice(Company $company, array $arguments): ?Invoice
    {
        $id = $arguments['id'] ?? null;
        $number = $arguments['number'] ?? null;

        $invoice = null;

        if (is_string($id) && '' !== $id) {
            $invoice = $this->em->getRepository(Invoice::class)->find($id);
        } elseif (is_string($number) && '' !== $number) {
            $invoice = $this->em->getRepository(Invoice::class)->findOneBy([
                'number' => $number,
            ]);
        }

        if (null === $invoice) {
            return null;
        }

        // Verifier que la facture appartient a l'entreprise de l'utilisateur
        if ($invoice->getSeller()->getId()?->toRfc4122() !== $company->getId()?->toRfc4122()) {
            return null;
        }

        return $invoice;
    }

    /**
     * Formate un resultat textuel pour le protocole MCP.
     *
     * @return ToolResult
     */
    private function textResult(string $text): array
    {
        return [
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
        ];
    }

    /**
     * Formate un message d'erreur pour le protocole MCP.
     *
     * @return ToolResult
     */
    private function errorResult(string $message): array
    {
        return [
            'content' => [
                ['type' => 'text', 'text' => $message],
            ],
            'isError' => true,
        ];
    }
}
