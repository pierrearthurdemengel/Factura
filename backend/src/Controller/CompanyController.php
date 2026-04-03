<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Endpoint pour acceder a l'entreprise de l'utilisateur connecte.
 */
class CompanyController extends AbstractController
{
    /**
     * Retourne l'entreprise de l'utilisateur connecte.
     */
    #[Route('/api/companies/me', name: 'api_company_me', methods: ['GET'])]
    public function me(SerializerInterface $serializer): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (null === $company) {
            return new JsonResponse(['error' => 'Aucune entreprise associee.'], Response::HTTP_NOT_FOUND);
        }

        $json = $serializer->serialize($company, 'jsonld', ['groups' => ['company:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }
}
