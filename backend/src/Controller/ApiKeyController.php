<?php

namespace App\Controller;

use App\Entity\ApiKey;
use App\Entity\Company;
use App\Entity\User;
use App\Service\Api\ApiKeyManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion des cles d'API pour l'acces programmatique.
 *
 * Permet de generer, lister et revoquer les cles d'API.
 * La cle en clair n'est retournee qu'une seule fois lors de la creation.
 */
#[Route('/api/keys')]
class ApiKeyController extends AbstractController
{
    public function __construct(
        private readonly ApiKeyManager $apiKeyManager,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Genere une nouvelle cle d'API.
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array{company_id?: string, name?: string, plan?: string, scopes?: list<string>} $data */
        $data = json_decode((string) $request->getContent(), true) ?? [];

        $validationError = $this->validateCreateKey($data);
        if (null !== $validationError) {
            return $validationError;
        }

        /** @var Company $company */
        $company = $this->getCompany($data['company_id'] ?? '');
        $name = $data['name'] ?? '';
        $plan = $data['plan'] ?? ApiKey::PLAN_FREE;
        /** @var list<string> $scopes */
        $scopes = $data['scopes'] ?? [];

        $result = $this->apiKeyManager->generate($user, $company, $name, $plan, $scopes);

        return new JsonResponse([
            'id' => (string) $result['apiKey']->getId(),
            'name' => $result['apiKey']->getName(),
            'keyPrefix' => $result['apiKey']->getKeyPrefix(),
            'plan' => $result['apiKey']->getPlan(),
            'rateLimit' => $result['apiKey']->getRateLimit(),
            'key' => $result['plainKey'],
        ], Response::HTTP_CREATED);
    }

    /**
     * Valide les donnees de creation d'une cle d'API.
     *
     * @param array<string, mixed> $data
     */
    private function validateCreateKey(array $data): ?JsonResponse
    {
        $company = $this->getCompany($data['company_id'] ?? '');
        if (null === $company) {
            return new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $name = $data['name'] ?? '';
        if ('' === $name) {
            return new JsonResponse(['error' => 'Le nom de la cle est requis.'], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    /**
     * Liste les cles d'API de l'utilisateur.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $company = $this->getCompany($request->query->getString('company_id'));
        if (null === $company) {
            return new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $keys = $this->apiKeyManager->listKeys($user, $company);
        $result = [];

        foreach ($keys as $key) {
            $result[] = [
                'id' => (string) $key->getId(),
                'name' => $key->getName(),
                'keyPrefix' => $key->getKeyPrefix(),
                'plan' => $key->getPlan(),
                'rateLimit' => $key->getRateLimit(),
                'active' => $key->isActive(),
                'requestCount' => $key->getRequestCount(),
                'lastUsedAt' => $key->getLastUsedAt()?->format('c'),
                'createdAt' => $key->getCreatedAt()->format('c'),
            ];
        }

        return new JsonResponse(['keys' => $result]);
    }

    /**
     * Revoque une cle d'API.
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function revoke(string $id): JsonResponse
    {
        $apiKey = $this->em->getRepository(ApiKey::class)->find($id);
        if (null === $apiKey) {
            return new JsonResponse(['error' => 'Cle non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $this->apiKeyManager->revoke($apiKey);

        return new JsonResponse(['message' => 'Cle revoquee.']);
    }

    private function getCompany(string $companyId): ?Company
    {
        if ('' === $companyId) {
            return null;
        }

        return $this->em->getRepository(Company::class)->find($companyId);
    }
}
