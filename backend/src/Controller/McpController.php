<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Mcp\McpServer;
use App\Service\Mcp\McpToolRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controleur MCP (Model Context Protocol) — Streamable HTTP.
 * Expose les tools de Ma Facture Pro aux LLM via JSON-RPC 2.0 sur HTTP.
 *
 * Le protocole MCP utilise un unique endpoint POST qui recoit des messages
 * JSON-RPC et retourne les reponses. L'initialisation, la decouverte de tools
 * et l'execution passent tous par ce meme endpoint.
 */
class McpController extends AbstractController
{
    public function __construct(
        private readonly McpToolRegistry $toolRegistry,
        private readonly McpServer $mcpServer,
    ) {
    }

    /**
     * Endpoint principal MCP — Streamable HTTP.
     * Recoit un message JSON-RPC 2.0, dispatche selon la methode.
     */
    #[Route('/mcp', name: 'mcp_endpoint', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        $body = $request->getContent();
        if ('' === $body) {
            return $this->jsonRpcError(null, -32700, 'Parse error');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['method'])) {
            return $this->jsonRpcError(null, -32600, 'Invalid Request');
        }

        $id = $decoded['id'] ?? null;
        $method = $decoded['method'];
        /** @var array<string, mixed> $params */
        $params = is_array($decoded['params'] ?? null) ? $decoded['params'] : [];

        return match ($method) {
            'initialize' => $this->handleInitialize($id),
            'tools/list' => $this->handleToolsList($id),
            'tools/call' => $this->handleToolsCall($id, $params),
            'ping' => $this->handlePing($id),
            default => $this->isNotification($decoded)
                ? new JsonResponse(null, Response::HTTP_NO_CONTENT)
                : $this->jsonRpcError($id, -32601, "Methode inconnue : {$method}"),
        };
    }

    /**
     * Initialisation MCP — retourne les capacites du serveur.
     */
    private function handleInitialize(mixed $id): JsonResponse
    {
        return $this->jsonRpcResult($id, [
            'protocolVersion' => '2025-03-26',
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => 'ma-facture-pro',
                'version' => '1.0.0',
            ],
        ]);
    }

    /**
     * Liste des tools disponibles.
     */
    private function handleToolsList(mixed $id): JsonResponse
    {
        return $this->jsonRpcResult($id, [
            'tools' => $this->toolRegistry->getTools(),
        ]);
    }

    /**
     * Execution d'un tool.
     *
     * @param array<string, mixed> $params
     */
    private function handleToolsCall(mixed $id, array $params): JsonResponse
    {
        $toolName = $params['name'] ?? null;
        if (!is_string($toolName) || '' === $toolName) {
            return $this->jsonRpcError($id, -32602, 'Parametre "name" manquant.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonRpcError($id, -32000, 'Authentification requise.');
        }

        /** @var array<string, mixed> $arguments */
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        return $this->executeToolCall($id, $toolName, $arguments, $user);
    }

    /**
     * Execute le tool MCP et retourne la reponse JSON-RPC.
     *
     * @param array<string, mixed> $arguments
     */
    private function executeToolCall(mixed $id, string $toolName, array $arguments, User $user): JsonResponse
    {
        try {
            $result = $this->mcpServer->executeTool($toolName, $arguments, $user);
        } catch (\Throwable $e) {
            $result = [
                'content' => [
                    ['type' => 'text', 'text' => 'Erreur interne : ' . $e->getMessage()],
                ],
                'isError' => true,
            ];
        }

        return $this->jsonRpcResult($id, $result);
    }

    /**
     * Repond au ping MCP.
     */
    private function handlePing(mixed $id): JsonResponse
    {
        return $this->jsonRpcResult($id, []);
    }

    /**
     * Formate une reponse JSON-RPC 2.0 avec resultat.
     */
    private function jsonRpcResult(mixed $id, mixed $result): JsonResponse
    {
        return new JsonResponse([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    /**
     * Formate une reponse JSON-RPC 2.0 avec erreur.
     */
    private function jsonRpcError(mixed $id, int $code, string $message): JsonResponse
    {
        $statusCode = match ($code) {
            -32700, -32600 => Response::HTTP_BAD_REQUEST,
            -32601 => Response::HTTP_NOT_FOUND,
            -32000 => Response::HTTP_UNAUTHORIZED,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

        return new JsonResponse([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $statusCode);
    }

    /**
     * Verifie si un message JSON-RPC est une notification (pas d'id = pas de reponse attendue).
     *
     * @param array<string, mixed> $message
     */
    private function isNotification(array $message): bool
    {
        return !array_key_exists('id', $message);
    }
}
