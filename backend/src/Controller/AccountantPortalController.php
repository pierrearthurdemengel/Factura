<?php

namespace App\Controller;

use App\Entity\AccountantInvitation;
use App\Entity\AccountantProfile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gere le portail comptable multi-clients.
 *
 * Permet aux experts-comptables de gerer plusieurs entreprises
 * clientes depuis un seul compte.
 */
class AccountantPortalController extends AbstractController
{
    private const MSG_PROFILE_NOT_FOUND = 'Profil comptable non trouve';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Retourne la vue consolidee multi-clients du comptable.
     */
    #[Route('/api/accountant/dashboard', name: 'api_accountant_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $profile = $this->getAccountantProfile();
        if (null === $profile) {
            return new JsonResponse(['error' => self::MSG_PROFILE_NOT_FOUND], Response::HTTP_FORBIDDEN);
        }

        $companies = [];
        foreach ($profile->getCompanies() as $company) {
            $companies[] = [
                'id' => $company->getId()?->toRfc4122(),
                'name' => $company->getName(),
                'siren' => $company->getSiren(),
            ];
        }

        return new JsonResponse([
            'firmName' => $profile->getFirmName(),
            'clientCount' => $profile->getClientCount(),
            'companies' => $companies,
        ]);
    }

    /**
     * Invite un client a lier son entreprise au cabinet.
     */
    #[Route('/api/accountant/invite', name: 'api_accountant_invite', methods: ['POST'])]
    public function invite(Request $request): JsonResponse
    {
        $profile = $this->getAccountantProfile();
        if (null === $profile) {
            return new JsonResponse(['error' => self::MSG_PROFILE_NOT_FOUND], Response::HTTP_FORBIDDEN);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $email = isset($data['email']) ? (string) $data['email'] : '';

        if ('' === $email || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(
                ['error' => 'Adresse email invalide.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Verifier qu'une invitation n'est pas deja en attente pour cet email
        $existing = $this->em->getRepository(AccountantInvitation::class)->findOneBy([
            'accountantProfile' => $profile,
            'email' => $email,
            'status' => AccountantInvitation::STATUS_PENDING,
        ]);

        if (null !== $existing && !$existing->isExpired()) {
            return new JsonResponse(
                ['error' => 'Une invitation est deja en attente pour cette adresse.'],
                Response::HTTP_CONFLICT,
            );
        }

        $invitation = new AccountantInvitation();
        $invitation->setAccountantProfile($profile);
        $invitation->setEmail($email);

        $this->em->persist($invitation);
        $this->em->flush();

        return new JsonResponse([
            'id' => $invitation->getId()?->toRfc4122(),
            'email' => $invitation->getEmail(),
            'token' => $invitation->getToken(),
            'expiresAt' => $invitation->getExpiresAt()->format('c'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Accepte une invitation comptable (appele par le client).
     */
    #[Route('/api/accountant/accept/{token}', name: 'api_accountant_accept', methods: ['POST'])]
    public function acceptInvitation(string $token): JsonResponse
    {
        $validationError = $this->validateAcceptInvitation($token);
        if (null !== $validationError) {
            return $validationError;
        }

        /** @var User $user */
        $user = $this->getUser();
        /** @var \App\Entity\Company $company */
        $company = $user->getCompany();

        /** @var AccountantInvitation $invitation */
        $invitation = $this->em->getRepository(AccountantInvitation::class)->findOneBy([
            'token' => $token,
        ]);

        $invitation->accept($company);
        $this->em->flush();

        return new JsonResponse([
            'status' => 'accepted',
            'firmName' => $invitation->getAccountantProfile()->getFirmName(),
        ]);
    }

    /**
     * Valide les preconditions pour accepter une invitation comptable.
     */
    private function validateAcceptInvitation(string $token): ?JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifie'], Response::HTTP_UNAUTHORIZED);
        }

        if (null === $user->getCompany()) {
            return new JsonResponse(['error' => 'Aucune entreprise configuree'], Response::HTTP_BAD_REQUEST);
        }

        /** @var AccountantInvitation|null $invitation */
        $invitation = $this->em->getRepository(AccountantInvitation::class)->findOneBy([
            'token' => $token,
        ]);

        if (null === $invitation) {
            return new JsonResponse(['error' => 'Invitation introuvable'], Response::HTTP_NOT_FOUND);
        }

        if (!$invitation->isAcceptable()) {
            return new JsonResponse(
                ['error' => 'Cette invitation a expire ou a deja ete utilisee.'],
                Response::HTTP_GONE,
            );
        }

        return null;
    }

    /**
     * Liste les invitations du cabinet.
     */
    #[Route('/api/accountant/invitations', name: 'api_accountant_invitations', methods: ['GET'])]
    public function listInvitations(): JsonResponse
    {
        $profile = $this->getAccountantProfile();
        if (null === $profile) {
            return new JsonResponse(['error' => self::MSG_PROFILE_NOT_FOUND], Response::HTTP_FORBIDDEN);
        }

        $invitations = [];
        foreach ($profile->getInvitations() as $invitation) {
            $invitations[] = [
                'id' => $invitation->getId()?->toRfc4122(),
                'email' => $invitation->getEmail(),
                'status' => $invitation->getStatus(),
                'createdAt' => $invitation->getCreatedAt()->format('c'),
                'expiresAt' => $invitation->getExpiresAt()->format('c'),
                'acceptedAt' => $invitation->getAcceptedAt()?->format('c'),
            ];
        }

        return new JsonResponse(['invitations' => $invitations]);
    }

    /**
     * Recupere le profil comptable de l'utilisateur courant.
     */
    private function getAccountantProfile(): ?AccountantProfile
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->em->getRepository(AccountantProfile::class)->findOneBy([
            'user' => $user,
        ]);
    }
}
