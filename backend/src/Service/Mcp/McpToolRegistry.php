<?php

namespace App\Service\Mcp;

/**
 * Registre des tools MCP disponibles pour les LLM.
 * Chaque tool est decrit par un schema JSON et des annotations de securite.
 *
 * @phpstan-type ToolDefinition array{name: string, description: string, inputSchema: array<string, mixed>, annotations: array{readOnlyHint: bool, destructiveHint: bool, openWorldHint: bool}}
 */
class McpToolRegistry
{
    private const DESC_INVOICE_UUID = 'Invoice UUID';
    private const DESC_INVOICE_NUMBER_ALT = 'Invoice number (alternative to id)';

    /**
     * Retourne la liste complete des tools MCP avec leurs schemas.
     *
     * @return list<ToolDefinition>
     */
    public function getTools(): array
    {
        return array_merge(
            $this->getClientTools(),
            $this->getInvoiceTools(),
            $this->getInvoiceActionTools(),
            $this->getDashboardTools(),
        );
    }

    /**
     * Tools de gestion des clients.
     *
     * @return list<ToolDefinition>
     */
    private function getClientTools(): array
    {
        return [
            [
                'name' => 'list_clients',
                'description' => 'List all clients of the user\'s company. Optionally filter by name.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'Optional search term to filter clients by name',
                        ],
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'create_client',
                'description' => 'Create a new client for the user\'s company.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Company name of the client'],
                        'siren' => ['type' => 'string', 'description' => 'SIREN number (9 digits, optional)'],
                        'addressLine1' => ['type' => 'string', 'description' => 'Street address'],
                        'postalCode' => ['type' => 'string', 'description' => 'Postal code'],
                        'city' => ['type' => 'string', 'description' => 'City name'],
                        'countryCode' => ['type' => 'string', 'description' => 'ISO country code (default: FR)'],
                    ],
                    'required' => ['name', 'addressLine1', 'postalCode', 'city'],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'openWorldHint' => false,
                ],
            ],
        ];
    }

    /**
     * Tools de consultation des factures.
     *
     * @return list<ToolDefinition>
     */
    private function getInvoiceTools(): array
    {
        return [
            [
                'name' => 'list_invoices',
                'description' => 'List invoices of the user\'s company. Can filter by status.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'description' => 'Filter by status: DRAFT, SENT, ACKNOWLEDGED, REJECTED, PAID, CANCELLED',
                            'enum' => ['DRAFT', 'SENT', 'ACKNOWLEDGED', 'REJECTED', 'PAID', 'CANCELLED'],
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of invoices to return (default: 20)',
                        ],
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'get_invoice',
                'description' => 'Get details of a specific invoice by its ID or number (e.g. FA-2026-0001).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => self::DESC_INVOICE_UUID],
                        'number' => ['type' => 'string', 'description' => 'Invoice number (e.g. FA-2026-0001)'],
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'create_invoice',
                'description' => 'Create a new invoice. Resolves client by name. Computes totals automatically.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'clientName' => ['type' => 'string', 'description' => 'Name of the client (must exist)'],
                        'issueDate' => ['type' => 'string', 'description' => 'Issue date (YYYY-MM-DD, default: today)'],
                        'dueDate' => ['type' => 'string', 'description' => 'Due date (YYYY-MM-DD, optional)'],
                        'paymentTerms' => ['type' => 'string', 'description' => 'Payment terms text (optional)'],
                        'lines' => [
                            'type' => 'array',
                            'description' => 'Invoice lines',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'description' => ['type' => 'string'],
                                    'quantity' => ['type' => 'string', 'description' => 'Quantity (e.g. "5")'],
                                    'unit' => ['type' => 'string', 'description' => 'Unit: DAY, HOUR, PIECE, etc. (default: DAY)'],
                                    'unitPriceExcludingTax' => ['type' => 'string', 'description' => 'Unit price excl. tax (e.g. "600.00")'],
                                    'vatRate' => ['type' => 'string', 'description' => 'VAT rate: 0, 5.5, 10, 20 (default: "20")'],
                                ],
                                'required' => ['description', 'quantity', 'unitPriceExcludingTax'],
                            ],
                        ],
                    ],
                    'required' => ['clientName', 'lines'],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'get_invoice_pdf_url',
                'description' => 'Get the download URL for a Factur-X PDF of an invoice.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => self::DESC_INVOICE_UUID],
                        'number' => ['type' => 'string', 'description' => self::DESC_INVOICE_NUMBER_ALT],
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'openWorldHint' => false,
                ],
            ],
        ];
    }

    /**
     * Tools d'actions sur les factures (envoi, paiement, annulation).
     *
     * @return list<ToolDefinition>
     */
    private function getInvoiceActionTools(): array
    {
        return [
            [
                'name' => 'send_invoice',
                'description' => 'Send a DRAFT invoice. This generates the sequential number (FA-YYYY-NNNN), transmits to the PDP, and archives the invoice. This action is irreversible.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => self::DESC_INVOICE_UUID],
                        'number' => ['type' => 'string', 'description' => self::DESC_INVOICE_NUMBER_ALT],
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => true,
                    'openWorldHint' => true,
                ],
            ],
            [
                'name' => 'mark_invoice_paid',
                'description' => 'Mark an invoice as paid.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => self::DESC_INVOICE_UUID],
                        'number' => ['type' => 'string', 'description' => self::DESC_INVOICE_NUMBER_ALT],
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => true,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'cancel_invoice',
                'description' => 'Cancel an invoice. Only possible for DRAFT, SENT, or ACKNOWLEDGED invoices.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => self::DESC_INVOICE_UUID],
                        'number' => ['type' => 'string', 'description' => self::DESC_INVOICE_NUMBER_ALT],
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => true,
                    'openWorldHint' => false,
                ],
            ],
        ];
    }

    /**
     * Tools de consultation du tableau de bord.
     *
     * @return list<ToolDefinition>
     */
    private function getDashboardTools(): array
    {
        return [
            [
                'name' => 'get_dashboard_stats',
                'description' => 'Get billing statistics for the current month: number of invoices, total revenue excl. tax, pending invoices, paid invoices.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'openWorldHint' => false,
                ],
            ],
        ];
    }
}
