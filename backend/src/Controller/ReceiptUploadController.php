<?php

namespace App\Controller;

use App\Entity\Receipt;
use App\Entity\User;
use App\Message\ExtractReceiptDataMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gere l'upload des justificatifs de depense.
 *
 * Stocke le fichier localement (en prod : S3) et dispatche
 * un message pour l'extraction OCR asynchrone.
 */
class ReceiptUploadController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
    ];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 Mo

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
        private readonly string $receiptUploadDir,
    ) {
    }

    /**
     * Upload un justificatif et lance l'extraction OCR asynchrone.
     */
    #[Route('/api/receipts/upload', name: 'api_receipt_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $validationError = $this->validateUpload($request);
        if (null !== $validationError) {
            return $validationError;
        }

        /** @var User $user */
        $user = $this->getUser();
        /** @var \App\Entity\Company $company */
        $company = $user->getCompany();
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
        $file = $request->files->get('receipt');

        /** @var string $mimeType */
        $mimeType = $file->getMimeType();

        // Hash du fichier original (valeur probante)
        $tempPath = $file->getPathname();
        $fileHash = hash_file('sha256', $tempPath);
        if (false === $fileHash) {
            return new JsonResponse(
                ['error' => 'Impossible de calculer le hash du fichier.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        // Stocker le fichier
        $extension = $file->guessExtension() ?? 'pdf';
        $filename = sprintf('receipt_%s_%s.%s', $company->getId()?->toRfc4122(), uniqid(), $extension);
        $file->move($this->receiptUploadDir, $filename);

        $filePath = $this->receiptUploadDir . '/' . $filename;

        // Creer l'entite Receipt
        $receipt = new Receipt();
        $receipt->setCompany($company);
        $receipt->setFilePath($filePath);
        $receipt->setOriginalFilename($file->getClientOriginalName() ?? $filename);
        $receipt->setMimeType($mimeType);
        $receipt->setFileSize($file->getSize() ?? 0);
        $receipt->setFileHash($fileHash);

        $this->em->persist($receipt);
        $this->em->flush();

        // Dispatcher l'extraction OCR asynchrone
        $this->messageBus->dispatch(new ExtractReceiptDataMessage(
            $receipt->getId()?->toRfc4122() ?? '',
        ));

        return new JsonResponse([
            'id' => $receipt->getId()?->toRfc4122(),
            'originalFilename' => $receipt->getOriginalFilename(),
            'ocrStatus' => 'PENDING',
        ], Response::HTTP_CREATED);
    }

    /**
     * Valide les preconditions de l'upload d'un justificatif.
     */
    private function validateUpload(Request $request): ?JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User || null === $user->getCompany()) {
            return new JsonResponse(
                ['error' => !($user instanceof User) ? 'Non authentifie' : 'Aucune entreprise configuree'],
                !($user instanceof User) ? Response::HTTP_UNAUTHORIZED : Response::HTTP_BAD_REQUEST,
            );
        }

        $file = $request->files->get('receipt');
        if (null === $file) {
            return new JsonResponse(['error' => 'Aucun fichier envoye'], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = $file->getMimeType();
        if (null === $mimeType || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return new JsonResponse(
                ['error' => 'Format non accepte. Formats autorises : PDF, PNG, JPG.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return new JsonResponse(
                ['error' => 'Fichier trop volumineux. Taille maximale : 10 Mo.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return null;
    }
}
