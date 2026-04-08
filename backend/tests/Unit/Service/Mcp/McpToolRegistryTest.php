<?php

namespace App\Tests\Unit\Service\Mcp;

use App\Service\Mcp\McpToolRegistry;
use PHPUnit\Framework\TestCase;

class McpToolRegistryTest extends TestCase
{
    private McpToolRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new McpToolRegistry();
    }

    public function testGetToolsReturnsNonEmptyList(): void
    {
        $tools = $this->registry->getTools();

        $this->assertNotEmpty($tools);
        $this->assertGreaterThanOrEqual(10, count($tools));
    }

    public function testEveryToolHasRequiredFields(): void
    {
        foreach ($this->registry->getTools() as $tool) {
            $this->assertArrayHasKey('name', $tool, 'Chaque tool doit avoir un nom');
            $this->assertArrayHasKey('description', $tool, "Tool {$tool['name']} manque description");
            $this->assertArrayHasKey('inputSchema', $tool, "Tool {$tool['name']} manque inputSchema");
            $this->assertArrayHasKey('annotations', $tool, "Tool {$tool['name']} manque annotations");
        }
    }

    public function testAnnotationsHaveSecurityHints(): void
    {
        foreach ($this->registry->getTools() as $tool) {
            $annotations = $tool['annotations'];
            $name = $tool['name'];
            $this->assertArrayHasKey('readOnlyHint', $annotations, "Tool {$name} manque readOnlyHint");
            $this->assertArrayHasKey('destructiveHint', $annotations, "Tool {$name} manque destructiveHint");
            $this->assertArrayHasKey('openWorldHint', $annotations, "Tool {$name} manque openWorldHint");
        }
    }

    public function testListClientsIsReadOnly(): void
    {
        $tools = $this->registry->getTools();
        $listClients = $this->findToolByName($tools, 'list_clients');

        $this->assertNotNull($listClients);
        $this->assertTrue($listClients['annotations']['readOnlyHint']);
        $this->assertFalse($listClients['annotations']['destructiveHint']);
    }

    public function testSendInvoiceIsDestructive(): void
    {
        $tools = $this->registry->getTools();
        $sendInvoice = $this->findToolByName($tools, 'send_invoice');

        $this->assertNotNull($sendInvoice);
        $this->assertFalse($sendInvoice['annotations']['readOnlyHint']);
        $this->assertTrue($sendInvoice['annotations']['destructiveHint']);
    }

    public function testCancelInvoiceIsDestructive(): void
    {
        $tools = $this->registry->getTools();
        $cancelInvoice = $this->findToolByName($tools, 'cancel_invoice');

        $this->assertNotNull($cancelInvoice);
        $this->assertTrue($cancelInvoice['annotations']['destructiveHint']);
    }

    public function testGetDashboardStatsIsReadOnly(): void
    {
        $tools = $this->registry->getTools();
        $stats = $this->findToolByName($tools, 'get_dashboard_stats');

        $this->assertNotNull($stats);
        $this->assertTrue($stats['annotations']['readOnlyHint']);
    }

    public function testCreateInvoiceHasRequiredFields(): void
    {
        $tools = $this->registry->getTools();
        $createInvoice = $this->findToolByName($tools, 'create_invoice');

        $this->assertNotNull($createInvoice);
        $schema = $createInvoice['inputSchema'];
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('clientName', $schema['properties']);
        $this->assertArrayHasKey('lines', $schema['properties']);
    }

    public function testToolNamesAreUnique(): void
    {
        $names = array_map(fn (array $t) => $t['name'], $this->registry->getTools());

        $this->assertSame($names, array_unique($names), 'Les noms de tools doivent etre uniques');
    }

    /**
     * @param list<array{name: string, description: string, inputSchema: array<string, mixed>, annotations: array<string, mixed>}> $tools
     *
     * @return array{name: string, description: string, inputSchema: array<string, mixed>, annotations: array<string, mixed>}|null
     */
    private function findToolByName(array $tools, string $name): ?array
    {
        foreach ($tools as $tool) {
            if ($tool['name'] === $name) {
                return $tool;
            }
        }

        return null;
    }
}
