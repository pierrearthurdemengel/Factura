<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Gere le switch d'entreprise active pour les utilisateurs multi-entite.
 */
class CompanySwitchController extends AbstractController
{
    /**
     * Change l'entreprise active de l'utilisateur connecte.
     *
     * Verifie que l'entreprise cible appartient bien a l'utilisateur
     * avant d'effectuer le changement.
     */
    #[Route('/api/companies/{id}/switch', name: 'api_company_switch', methods: ['POST'])]
    public function switch(
        string $id,
        EntityManagerInterface $em,
        SerializerInterface $serializer,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $company = $em->getRepository(Company::class)->find($id);

        if (null === $company) {
            return new JsonResponse(['error' => 'Entreprise introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Verifier que l'entreprise appartient a l'utilisateur
        if ($company->getOwner()->getId()?->toRfc4122() !== $user->getId()?->toRfc4122()) {
            return new JsonResponse(['error' => 'Acces interdit.'], Response::HTTP_FORBIDDEN);
        }

        $user->setActiveCompany($company);
        $em->flush();

        $json = $serializer->serialize($company, 'jsonld', ['groups' => ['company:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    /**
     * Retourne la liste de toutes les entreprises de l'utilisateur connecte.
     */
    #[Route('/api/companies/list', name: 'api_company_list', methods: ['GET'])]
    public function list(SerializerInterface $serializer): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $companies = $user->getCompanies()->toArray();

        $json = $serializer->serialize($companies, 'json', ['groups' => ['company:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }
}
