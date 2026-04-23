<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gere l'upload du logo de l'entreprise.
 * Stocke le logo dans le repertoire local (en prod : S3 via le service d'archivage).
 */
class CompanyLogoController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/svg+xml'];
    private const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 Mo

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $uploadDir,
    ) {
    }

    #[Route('/api/companies/{id}/logo', name: 'company_upload_logo', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $validationError = $this->validateLogoUpload($request);
        if (null !== $validationError) {
            return $validationError;
        }

        /** @var User $user */
        $user = $this->getUser();
        /** @var \App\Entity\Company $company */
        $company = $user->getCompany();
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
        $file = $request->files->get('logo');

        // Generer un nom unique et stocker le fichier
        $extension = $file->guessExtension() ?? 'png';
        $filename = sprintf('logo_%s.%s', $company->getId()?->toRfc4122(), $extension);

        $file->move($this->uploadDir, $filename);

        $logoPath = $this->uploadDir . '/' . $filename;
        $company->setLogoPath($logoPath);
        $this->em->flush();

        return new JsonResponse(['logoPath' => $logoPath], Response::HTTP_OK);
    }

    /**
     * Valide les preconditions de l'upload du logo.
     */
    private function validateLogoUpload(Request $request): ?JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User || null === $user->getCompany()) {
            return new JsonResponse(
                ['error' => !($user instanceof User) ? 'Non authentifie' : 'Aucune entreprise configuree'],
                !($user instanceof User) ? Response::HTTP_UNAUTHORIZED : Response::HTTP_BAD_REQUEST,
            );
        }

        $file = $request->files->get('logo');
        if (null === $file) {
            return new JsonResponse(['error' => 'Aucun fichier envoye'], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = $file->getMimeType();
        if (null === $mimeType || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return new JsonResponse(
                ['error' => 'Format non accepte. Formats autorises : PNG, JPG, SVG.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return new JsonResponse(
                ['error' => 'Fichier trop volumineux. Taille maximale : 2 Mo.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return null;
    }
}
