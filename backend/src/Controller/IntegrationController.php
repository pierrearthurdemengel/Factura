<?php

namespace App\Controller;

use App\Entity\OAuthAccessToken;
use App\Entity\User;
use App\Service\OAuth\OAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion des integrations LLM connectees au compte de l'utilisateur.
 * Permet de lister et revoquer les connexions OAuth.
 */
class IntegrationController extends AbstractController
{
    public function __construct(
        private readonly OAuthService $oauthService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Liste les LLM connectes au compte de l'utilisateur.
     * Retourne un tableau de tokens actifs avec le nom du client.
     */
    #[Route('/api/integrations', name: 'api_integrations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $tokens = $this->oauthService->getActiveIntegrations($user);

        $result = array_map(fn (OAuthAccessToken $token) => [
            'id' => $token->getId()?->toRfc4122(),
            'clientName' => $token->getClient()->getName(),
            'scopes' => $token->getScopes(),
            'connectedAt' => $token->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'lastUsedAt' => $token->getLastUsedAt()?->format(\DateTimeInterface::ATOM),
        ], $tokens);

        return new JsonResponse($result);
    }

    /**
     * Revoque une integration (deconnecte un LLM).
     */
    #[Route('/api/integrations/{id}', name: 'api_integrations_revoke', methods: ['DELETE'])]
    public function revoke(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $accessToken = $this->em->getRepository(OAuthAccessToken::class)->find($id);

        if (null === $accessToken) {
            return new JsonResponse(['error' => 'Integration introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Verifier que le token appartient a l'utilisateur connecte
        if ($accessToken->getUser()->getId()?->toRfc4122() !== $user->getId()?->toRfc4122()) {
            return new JsonResponse(['error' => 'Acces refuse.'], Response::HTTP_FORBIDDEN);
        }

        $this->oauthService->revokeAccessToken($accessToken);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
