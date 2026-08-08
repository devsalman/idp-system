<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrgUnitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrgUnitController extends AbstractController
{
    #[Route('/org-units', name: 'app_org_unit_index')]
    public function index(OrgUnitRepository $orgUnitRepository): Response
    {
        return $this->render('org_unit/index.html.twig', [
            'org_units' => $orgUnitRepository->findAllOrderedByCode(),
        ]);
    }
}
