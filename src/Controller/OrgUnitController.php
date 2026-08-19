<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OrgUnit;
use App\Form\OrgUnitType;
use App\Repository\OrgUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/org-unit', name: 'app_org_unit_new', methods: ['GET', 'POST'])]
    public function formNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        $orgUnit = new OrgUnit();
        $form = $this->createForm(OrgUnitType::class, $orgUnit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $orgUnit = $form->getData();
            $entityManager->persist($orgUnit);
            $entityManager->flush();

            $this->addFlash('success', 'Unit organisasi berhasil ditambahkan.');

            return $this->redirectToRoute('app_org_unit_index');
        }

        return $this->render('org_unit/form_new.html.twig', ['form' => $form]);
    }

    #[Route('/org-unit/{id}', name: 'app_org_unit_show', methods: ['GET', 'POST'])]
    public function formEdit(OrgUnitRepository $orgUnitRepository, EntityManagerInterface $entityManager, Request $request, int $id): Response
    {
        $orgUnit = $orgUnitRepository->find($id);

        if (!$orgUnit instanceof OrgUnit) {
            throw $this->createNotFoundException('Unit organisasi tidak ditemukan.');
        }

        $form = $this->createForm(OrgUnitType::class, $orgUnit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($orgUnit);
            $entityManager->flush();

            $this->addFlash('success', 'Unit organisasi berhasil diperbarui.');

            return $this->redirectToRoute('app_org_unit_show', ['id' => $orgUnit->getId()]);
        }

        return $this->render('org_unit/form.html.twig', [
            'org_unit' => $orgUnit,
            'form' => $form,
        ]);
    }
}
