<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Subscription;
use App\Entity\User;
use App\Service\Auth\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    private const COOKIE_PATH_AUTH = '/api/auth';

    public function __construct(
        private readonly AuthTokenService $authTokenService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Point d'entree pour l'authentification JSON.
     *
     * Cette route est interceptee par le firewall json_login de Symfony
     * avant que le controller ne soit atteint. Le corps de la methode
     * n'est jamais execute.
     */
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Cette route est geree par le firewall json_login.');
    }

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
        $user->addCompany($company);

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

    /**
     * Renouvellement du JWT via le refresh token (cookie HTTP-only).
     *
     * Le refresh token est lu depuis le cookie, valide cote serveur,
     * puis une rotation est appliquee : l'ancien token est revoque
     * et un nouveau couple access/refresh est emis.
     */
    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $rawRefreshToken = $request->cookies->get('refresh_token');

        if (null === $rawRefreshToken || '' === $rawRefreshToken) {
            return new JsonResponse(
                ['error' => 'Refresh token manquant.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $result = $this->authTokenService->rotateRefreshToken($rawRefreshToken, $request);

        if (null === $result) {
            $this->logger->warning('Tentative de refresh avec un token invalide', [
                'ip' => $request->getClientIp(),
            ]);

            // Supprimer le cookie invalide
            $response = new JsonResponse(
                ['error' => 'Refresh token invalide ou expire.'],
                Response::HTTP_UNAUTHORIZED,
            );
            $response->headers->clearCookie('refresh_token', self::COOKIE_PATH_AUTH);

            return $response;
        }

        /** @var User $user */
        $user = $result['user'];
        $newRawRefreshToken = $result['newToken'];

        // Nouveau JWT d'acces
        $jwt = $this->jwtManager->create($user);

        $response = new JsonResponse(['token' => $jwt]);

        // Nouveau refresh token en cookie
        $response->headers->setCookie(
            Cookie::create('refresh_token')
                ->withValue($newRawRefreshToken)
                ->withExpires(new \DateTimeImmutable('+30 days'))
                ->withPath(self::COOKIE_PATH_AUTH)
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite('strict'),
        );

        return $response;
    }

    /**
     * Deconnexion : revoque le refresh token et supprime le cookie.
     */
    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $rawRefreshToken = $request->cookies->get('refresh_token');

        if (null !== $rawRefreshToken && '' !== $rawRefreshToken) {
            $this->authTokenService->revokeToken($rawRefreshToken);
        }

        $this->logger->info('Deconnexion', [
            'ip' => $request->getClientIp(),
        ]);

        $response = new JsonResponse(['message' => 'Deconnecte.']);
        $response->headers->clearCookie('refresh_token', self::COOKIE_PATH_AUTH);

        return $response;
    }

    /**
     * Retourne les informations de l'utilisateur connecte.
     *
     * Les donnees personnelles (email, nom, roles) ne sont plus dans le JWT.
     * Ce endpoint est le seul moyen de les recuperer cote client.
     */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse([
            'id' => $user->getId()?->toRfc4122(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
