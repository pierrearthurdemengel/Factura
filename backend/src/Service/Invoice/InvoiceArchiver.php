<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Service\Format\FacturXGenerator;
use App\Service\Format\UblGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Archive les factures emises dans un stockage S3 (Scaleway Object Storage).
 *
 * Calcule le hash SHA-256 du contenu XML, construit le chemin S3
 * {siren}/{year}/{invoice_number}/, et persiste les metadonnees en BDD.
 * Le versioning S3 doit etre active sur le bucket pour garantir
 * la retention legale de 10 ans.
 */
class InvoiceArchiver
{
    public function __construct(
        private readonly FacturXGenerator $facturXGenerator,
        private readonly UblGenerator $ublGenerator,
        private readonly EntityManagerInterface $em,
        private readonly string $s3Bucket,
        private readonly string $s3Region,
        private readonly string $s3Endpoint,
        private readonly string $s3Key,
        private readonly string $s3Secret,
    ) {
    }

    /**
     * Archive une facture : genere les formats XML, calcule le hash,
     * upload vers S3 et met a jour les champs en base.
     */
    public function archive(Invoice $invoice): void
    {
        $ciiXml = $this->facturXGenerator->generate($invoice);
        $ublXml = $this->ublGenerator->generate($invoice);

        // Hash SHA-256 du XML CII (source de verite)
        $hash = hash('sha256', $ciiXml);

        // Chemin S3 : {siren}/{year}/{invoice_number}/
        $seller = $invoice->getSeller();
        if (null === $seller) {
            throw new \RuntimeException('La facture doit avoir un vendeur pour etre archivee.');
        }
        $siren = $seller->getSiren();
        $year = $invoice->getIssueDate()->format('Y');
        $number = $invoice->getNumber() ?? 'draft';
        $basePath = sprintf('%s/%s/%s', $siren, $year, $number);

        // Upload des deux formats
        $this->uploadToS3($basePath . '/facturx.xml', $ciiXml);
        $this->uploadToS3($basePath . '/ubl.xml', $ublXml);

        // Mise a jour des metadonnees en base
        $invoice->setFileHash($hash);
        $invoice->setArchivedFilePath($basePath);

        $this->em->flush();
    }

    /**
     * Upload un fichier vers S3 via l'API REST S3.
     */
    private function uploadToS3(string $path, string $content): void
    {
        // Utilisation de l'API S3 via cURL pour eviter la dependance aws-sdk-php
        $date = gmdate('Ymd\THis\Z');
        $dateShort = gmdate('Ymd');
        $host = parse_url($this->s3Endpoint, PHP_URL_HOST);
        $url = sprintf('%s/%s/%s', rtrim($this->s3Endpoint, '/'), $this->s3Bucket, ltrim($path, '/'));

        $contentHash = hash('sha256', $content);

        // Headers canoniques
        $headers = [
            'content-type' => 'application/xml',
            'host' => $host,
            'x-amz-content-sha256' => $contentHash,
            'x-amz-date' => $date,
        ];

        ksort($headers);

        $canonicalHeaders = '';
        $signedHeaders = [];
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= $key . ':' . trim((string) $value) . "\n";
            $signedHeaders[] = $key;
        }
        $signedHeadersStr = implode(';', $signedHeaders);

        // Requete canonique
        $canonicalRequest = implode("\n", [
            'PUT',
            '/' . $this->s3Bucket . '/' . ltrim($path, '/'),
            '',
            $canonicalHeaders,
            $signedHeadersStr,
            $contentHash,
        ]);

        // Scope et signature
        $scope = sprintf('%s/%s/s3/aws4_request', $dateShort, $this->s3Region);
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $date,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        // Cle de signature
        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', 's3',
                hash_hmac('sha256', $this->s3Region,
                    hash_hmac('sha256', $dateShort, 'AWS4' . $this->s3Secret, true),
                    true),
                true),
            true);

        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->s3Key,
            $scope,
            $signedHeadersStr,
            $signature,
        );

        $ch = curl_init($url);
        if (false === $ch) {
            throw new \RuntimeException('Impossible d\'initialiser cURL pour l\'upload S3.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml',
                'Host: ' . $host,
                'X-Amz-Content-Sha256: ' . $contentHash,
                'X-Amz-Date: ' . $date,
                'Authorization: ' . $authorization,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException(sprintf('Erreur S3 lors de l\'upload de %s : HTTP %d — %s', $path, $httpCode, is_string($response) ? $response : ''));
        }
    }
}
