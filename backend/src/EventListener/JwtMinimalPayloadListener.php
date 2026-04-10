<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Reduit le payload JWT au strict minimum.
 *
 * Par defaut Lexik inclut l'email et les roles dans le token.
 * Pour des raisons de securite, seul l'identifiant utilisateur (sub),
 * la date d'emission (iat) et la date d'expiration (exp) sont conserves.
 * Les donnees personnelles doivent etre recuperees via GET /api/me.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
class JwtMinimalPayloadListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getData();

        // Conserver uniquement sub (identifiant), iat et exp
        $minimalPayload = [
            'sub' => $user->getUserIdentifier(),
            'iat' => $payload['iat'] ?? time(),
            'exp' => $payload['exp'] ?? time() + 900,
        ];

        $event->setData($minimalPayload);
    }
}
