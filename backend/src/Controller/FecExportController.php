<?php

namespace App\Controller;

use App\Entity\Company;
use App\Service\Tax\FecExporter;
use App\Service\Tax\FecValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint pour l'export du Fichier des Ecritures Comptables (FEC).
 *
 * Le FEC est obligatoire en cas de controle fiscal pour toute
 * entreprise tenant une comptabilite informatisee.
 */
class FecExportController extends AbstractController
{
    public function __construct(
        private readonly FecExporter $fecExporter,
        private readonly FecValidator $fecValidator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Exporte le FEC au format texte tab-separated.
     *
     * Le fichier est nomme selon la convention SirenFECAAAAMMJJ.txt.
     */
    #[Route('/api/exports/fec', methods: ['GET'])]
    public function exportFec(Request $request): Response
    {
        $company = $this->resolveCompany($request);
        if (null === $company) {
            $companyId = $request->query->getString('company_id');

            return '' === $companyId
                ? new JsonResponse(['error' => 'Parametre company_id requis.'], Response::HTTP_BAD_REQUEST)
                : new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $year = $request->query->getInt('year', (int) date('Y'));

        // Generer le FEC
        $fecContent = $this->fecExporter->export($company, $year);

        // Valider le FEC genere
        $validation = $this->fecValidator->validate($fecContent);

        // Si demande de validation uniquement
        if ($request->query->getBoolean('validate_only', false)) {
            return new JsonResponse($validation);
        }

        // Retourner le fichier FEC
        $fileName = $this->fecExporter->generateFileName($company, $year);

        $response = new Response($fecContent);
        $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $fileName));
        $response->headers->set('X-FEC-Valid', $validation['valid'] ? 'true' : 'false');
        $response->headers->set('X-FEC-Entries', (string) $validation['entryCount']);

        return $response;
    }

    /**
     * Resout l'entreprise depuis le parametre company_id de la requete.
     */
    private function resolveCompany(Request $request): ?Company
    {
        $companyId = $request->query->getString('company_id');
        if ('' === $companyId) {
            return null;
        }

        $company = $this->em->getRepository(Company::class)->find($companyId);

        return $company instanceof Company ? $company : null;
    }
}
