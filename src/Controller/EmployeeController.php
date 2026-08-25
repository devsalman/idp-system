<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Employee;
use App\Form\EmployeeType;
use App\Repository\EmployeeRepository;
use App\Service\CredentialMailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmployeeController extends AbstractController
{
    #[Route('/employees', name: 'app_employee_index')]
    public function index(EmployeeRepository $employeeRepository): Response
    {
        return $this->render('employee/index.html.twig', [
            'employees' => $employeeRepository->findAllOrderedByFullname(),
        ]);
    }

    #[Route('/employee', name: 'app_employee_new', methods: ['GET', 'POST'])]
    public function form(Request $request, EntityManagerInterface $entityManager): Response
    {
        $employee = new Employee();
        $form = $this->createForm(EmployeeType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $employee = $form->getData();
            $entityManager->persist($employee);
            $entityManager->flush();

            $this->addFlash('success', 'Data pegawai berhasil ditambahkan.');

            return $this->redirectToRoute('app_employee_show', ['id' => $employee->getId()]);
        }

        return $this->render('employee/form.html.twig', ['form' => $form]);
    }

    #[Route('/employees/{id}', name: 'app_employee_show', methods: ['GET', 'POST'])]
    public function show(EmployeeRepository $employeeRepository, EntityManagerInterface $entityManager, Request $request, int $id): Response
    {
        $employee = $employeeRepository->find($id);

        if (!$employee instanceof Employee) {
            throw $this->createNotFoundException('Pegawai tidak ditemukan.');
        }

        $form = $this->createForm(EmployeeType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($employee);
            $entityManager->flush();

            $this->addFlash('success', 'Data pegawai berhasil diperbarui.');

            return $this->redirectToRoute('app_employee_show', ['id' => $employee->getId()]);
        }

        return $this->render('employee/detail.html.twig', [
            'employee' => $employee,
            'form' => $form,
        ]);
    }

    #[Route('/employees/{id}/send-credential', name: 'app_employee_send_credential', methods: ['GET'])]
    public function sendCredential(int $id, EmployeeRepository $employeeRepository, CredentialMailService $mailService): Response
    {
        $employee = $employeeRepository->find($id);

        if (!$employee instanceof Employee) {
            throw $this->createNotFoundException('Pegawai tidak ditemukan.');
        }

        try {
            $mailService->sendCredentialRequest($employee);
            $this->addFlash('success', 'Email permintaan kredensial berhasil dikirim ke ' . $employee->getEmail() . '.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Gagal mengirim email: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_employee_show', ['id' => $employee->getId()]);
    }
}
