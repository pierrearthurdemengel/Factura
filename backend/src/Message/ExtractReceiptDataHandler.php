<?php

namespace App\Message;

use App\Entity\Receipt;
use App\Service\Ocr\OcrExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler Messenger : extrait les donnees d'un justificatif via OCR.
 *
 * Pipeline en deux etapes :
 * 1. Extraction du texte brut (Tesseract en prod)
 * 2. Structuration des donnees (via le service OcrExtractor)
 */
#[AsMessageHandler]
class ExtractReceiptDataHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OcrExtractor $ocrExtractor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExtractReceiptDataMessage $message): void
    {
        $receipt = $this->em->getRepository(Receipt::class)->find($message->getReceiptId());

        if (null === $receipt) {
            $this->logger->error('Justificatif introuvable pour extraction OCR.', [
                'receiptId' => $message->getReceiptId(),
            ]);

            return;
        }

        try {
            $receipt->setOcrStatus('PROCESSING');
            $this->em->flush();

            $ocrData = $this->ocrExtractor->extract($receipt->getFilePath(), $receipt->getMimeType());

            $receipt->setOcrData($ocrData);
            $receipt->setOcrStatus('COMPLETED');
            $this->em->flush();

            $this->logger->info('Extraction OCR terminee.', [
                'receiptId' => $message->getReceiptId(),
                'filename' => $receipt->getOriginalFilename(),
            ]);
        } catch (\Throwable $e) {
            $receipt->setOcrStatus('FAILED');
            $this->em->flush();

            $this->logger->error('Echec de l\'extraction OCR.', [
                'receiptId' => $message->getReceiptId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
