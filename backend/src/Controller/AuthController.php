<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    /**
     * Inscription d'un nouvel utilisateur avec creation de son entreprise.
     */
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Donnees invalides.'], Response::HTTP_BAD_REQUEST);
        }

        // Creation de l'utilisateur
        $user = new User();
        $user->setEmail($data['email'] ?? '');
        $user->setFirstName($data['firstName'] ?? '');
        $user->setLastName($data['lastName'] ?? '');
        $user->setPassword(
            $passwordHasher->hashPassword($user, $data['password'] ?? ''),
        );

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Creation de l'entreprise associee
        $company = new Company();
        $company->setName($data['companyName'] ?? '');
        $company->setSiren($data['siren'] ?? '');
        $company->setLegalForm($data['legalForm'] ?? 'EI');
        $company->setAddressLine1($data['addressLine1'] ?? '');
        $company->setPostalCode($data['postalCode'] ?? '');
        $company->setCity($data['city'] ?? '');
        $company->setOwner($user);
        $user->setCompany($company);

        // Creation de l'abonnement gratuit
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan('free');

        $em->persist($user);
        $em->persist($company);
        $em->persist($subscription);
        $em->flush();

        return new JsonResponse(
            ['message' => 'Compte cree avec succes.', 'userId' => $user->getId()?->toRfc4122()],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
