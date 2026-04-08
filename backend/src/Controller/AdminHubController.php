<?php

namespace App\Controller;

use App\Service\AdminHub\GovernmentApiDirectory;
use App\Service\AdminHub\InseeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Hub administratif centralisant les integrations gouvernementales.
 *
 * Fournit un point d'entree unique pour acceder aux services
 * URSSAF, DGFiP, INSEE et INPI depuis le dashboard.
 */
#[Route('/api/admin-hub')]
class AdminHubController extends AbstractController
{
    public function __construct(
        private readonly GovernmentApiDirectory $directory,
        private readonly InseeClient $inseeClient,
    ) {
    }

    /**
     * Liste tous les services administratifs disponibles.
     */
    #[Route('/services', methods: ['GET'])]
    public function services(): JsonResponse
    {
        return $this->json([
            'services' => $this->directory->getServices(),
            'categories' => $this->directory->getCategories(),
        ]);
    }

    /**
     * Liste les services d'une categorie donnee.
     */
    #[Route('/services/{category}', methods: ['GET'])]
    public function servicesByCategory(string $category): JsonResponse
    {
        $services = $this->directory->getServicesByCategory($category);

        if ([] === $services) {
            return $this->json(['error' => 'Categorie inconnue'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['services' => $services]);
    }

    /**
     * Recherche une entreprise par SIREN dans la base INSEE Sirene.
     */
    #[Route('/insee/siren/{siren}', methods: ['GET'])]
    public function inseeSearchBySiren(string $siren): JsonResponse
    {
        if (!$this->inseeClient->isConfigured()) {
            return $this->json(
                ['error' => 'API INSEE non configuree. Ajoutez INSEE_API_TOKEN dans .env.local.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $result = $this->inseeClient->findBySiren($siren);

        if (null === $result) {
            return $this->json(['error' => 'SIREN non trouve'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($result);
    }

    /**
     * Recherche un etablissement par SIRET dans la base INSEE Sirene.
     */
    #[Route('/insee/siret/{siret}', methods: ['GET'])]
    public function inseeSearchBySiret(string $siret): JsonResponse
    {
        if (!$this->inseeClient->isConfigured()) {
            return $this->json(
                ['error' => 'API INSEE non configuree. Ajoutez INSEE_API_TOKEN dans .env.local.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $result = $this->inseeClient->findBySiret($siret);

        if (null === $result) {
            return $this->json(['error' => 'SIRET non trouve'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($result);
    }

    /**
     * Recherche textuelle d'entreprises via l'API INSEE.
     */
    #[Route('/insee/search', methods: ['GET'])]
    public function inseeSearch(Request $request): JsonResponse
    {
        if (!$this->inseeClient->isConfigured()) {
            return $this->json(
                ['error' => 'API INSEE non configuree. Ajoutez INSEE_API_TOKEN dans .env.local.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $query = $request->query->getString('q', '');
        $limit = $request->query->getInt('limit', 10);

        if ('' === $query) {
            return $this->json(['error' => 'Parametre q requis'], Response::HTTP_BAD_REQUEST);
        }

        $results = $this->inseeClient->search($query, $limit);

        return $this->json(['results' => $results, 'count' => count($results)]);
    }
}
