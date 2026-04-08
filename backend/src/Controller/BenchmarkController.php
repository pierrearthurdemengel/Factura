<?php

namespace App\Controller;

use App\Service\Benchmark\BenchmarkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Expose les benchmarks sectoriels anonymises.
 * Toutes les donnees retournees sont agregees — aucune donnee individuelle.
 */
#[Route('/api/benchmarks')]
#[IsGranted('ROLE_USER')]
class BenchmarkController extends AbstractController
{
    public function __construct(
        private readonly BenchmarkService $benchmarkService,
    ) {
    }

    /**
     * Retourne les benchmarks disponibles pour le secteur de l'utilisateur.
     */
    #[Route('', name: 'api_benchmarks_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (null === $company) {
            return new JsonResponse([
                'error' => 'Aucune entreprise configuree.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $benchmarks = $this->benchmarkService->getBenchmarksForCompany($company);

        return new JsonResponse([
            'benchmarks' => $benchmarks,
        ]);
    }

    /**
     * Compare les performances de l'utilisateur aux benchmarks du secteur.
     */
    #[Route('/compare', name: 'api_benchmarks_compare', methods: ['GET'])]
    public function compare(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (null === $company) {
            return new JsonResponse([
                'error' => 'Aucune entreprise configuree.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $period = $request->query->getString('period', (new \DateTimeImmutable())->format('Y-m'));

        $comparison = $this->benchmarkService->compareToSector($company, $period);

        if (null === $comparison) {
            return new JsonResponse([
                'error' => 'Aucun benchmark disponible pour votre secteur.',
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($comparison);
    }
}
