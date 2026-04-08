<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controleur pour le mode white-label iframe.
 * Permet aux partenaires (neobanques, comptables) d'embarquer
 * des vues de facturation dans leurs applications.
 */
class EmbedController extends AbstractController
{
    /**
     * Retourne la configuration de theming validee pour un partenaire.
     * Les parametres de couleur, logo et police sont valides et sanitises.
     */
    #[Route('/api/embed/config', name: 'api_embed_config', methods: ['GET'])]
    public function config(Request $request): JsonResponse
    {
        $config = [
            'primaryColor' => $this->sanitizeColor($request->query->getString('primaryColor', '#2563eb')),
            'backgroundColor' => $this->sanitizeColor($request->query->getString('backgroundColor', '#ffffff')),
            'textColor' => $this->sanitizeColor($request->query->getString('textColor', '#111827')),
            'borderRadius' => min(max($request->query->getInt('borderRadius', 8), 0), 24),
            'fontFamily' => $this->sanitizeFont($request->query->getString('fontFamily', 'system-ui')),
            'logoUrl' => $this->sanitizeUrl($request->query->getString('logoUrl', '')),
            'locale' => $this->sanitizeLocale($request->query->getString('locale', 'fr')),
        ];

        return new JsonResponse($config);
    }

    /**
     * Endpoint de sante pour les integrateurs (verification iframe accessible).
     */
    #[Route('/api/embed/health', name: 'api_embed_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'version' => '1.0.0',
            'embed' => true,
        ]);
    }

    /**
     * Headers de securite pour les reponses iframe.
     * Configure les headers necessaires pour le fonctionnement en iframe
     * tout en maintenant la securite (CSP, referrer-policy).
     */
    #[Route('/embed/{path}', name: 'embed_frame', methods: ['GET'], requirements: ['path' => '.+'])]
    public function frame(string $path): Response
    {
        // L'iframe pointe vers les pages frontend avec un parametre embed=1
        $response = new Response('', Response::HTTP_OK, [
            'Content-Type' => 'text/html',
            'X-Frame-Options' => 'ALLOWALL',
            'Content-Security-Policy' => "frame-ancestors *",
            'Referrer-Policy' => 'no-referrer-when-downgrade',
        ]);

        // En production, le frontend est servi par le meme domaine
        // Cette route redirige vers la SPA avec le parametre embed
        $response->headers->set('Location', "/{$path}?embed=1");
        $response->setStatusCode(Response::HTTP_FOUND);

        return $response;
    }

    /**
     * Valide un code couleur hexadecimal.
     */
    private function sanitizeColor(string $color): string
    {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return $color;
        }

        return '#2563eb';
    }

    /**
     * Valide et sanitise un nom de police.
     */
    private function sanitizeFont(string $font): string
    {
        $allowed = ['system-ui', 'Inter', 'Roboto', 'Open Sans', 'Lato', 'Poppins', 'Montserrat'];

        return in_array($font, $allowed, true) ? $font : 'system-ui';
    }

    /**
     * Valide une URL de logo (HTTPS uniquement).
     */
    private function sanitizeUrl(string $url): string
    {
        if ('' === $url) {
            return '';
        }

        if (!str_starts_with($url, 'https://')) {
            return '';
        }

        // Filtrage basique pour eviter les injections
        if (filter_var($url, \FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $url;
    }

    /**
     * Valide un code de locale.
     */
    private function sanitizeLocale(string $locale): string
    {
        $allowed = ['fr', 'en', 'de', 'es', 'it'];

        return in_array($locale, $allowed, true) ? $locale : 'fr';
    }
}
