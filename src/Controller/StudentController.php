<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Student;
use App\Form\StudentType;
use App\Repository\StudentRepository;
use App\Service\CredentialMailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StudentController extends AbstractController
{
    #[Route("/students", name: "app_student_index")]
    public function index(StudentRepository $studentRepository): Response
    {
        return $this->render("student/index.html.twig", [
            "students" => $studentRepository->findAllOrderedByFullname(),
        ]);
    }

    #[Route("/student", name: "app_student_new", methods: ["GET", "POST"])]
    public function form(Request $request, EntityManagerInterface $entityManager): Response
    {
        $student = new Student();
        $form = $this->createForm(StudentType::class, $student);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $student = $form->getData();
            $entityManager->persist($student);
            $entityManager->flush();

            $this->addFlash('success', 'Data mahasiswa berhasil ditambahkan.');

            return $this->redirectToRoute("app_student_show", [
                "id" => $student->getId(),
            ]);
        }

        return $this->render("student/form.html.twig", ["form" => $form]);
    }

    #[Route("/students/{id}", name: "app_student_show", methods: ["GET", "POST"])]
    public function show(
        Request $request,
        StudentRepository $studentRepository,
        EntityManagerInterface $entityManager,
        int $id,
    ): Response {
        $student = $studentRepository->find($id);
        if (!$student instanceof Student) {
            throw $this->createNotFoundException("Mahasiswa tidak ditemukan.");
        }

        $form = $this->createForm(StudentType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($student);
            $entityManager->flush();

            $this->addFlash("success", "Data mahasiswa berhasil diperbarui.");

            return $this->redirectToRoute("app_student_show", [
                "id" => $student->getId(),
            ]);
        }

        return $this->render("student/detail.html.twig", [
            "student" => $student,
            "form" => $form,
        ]);
    }

    #[Route('/students/{id}/send-credential', name: 'app_student_send_credential', methods: ['GET'])]
    public function sendCredential(int $id, StudentRepository $studentRepository, CredentialMailService $mailService): Response
    {
        $student = $studentRepository->find($id);

        if (!$student instanceof Student) {
            throw $this->createNotFoundException('Mahasiswa tidak ditemukan.');
        }

        try {
            $mailService->sendCredentialRequest($student);
            $this->addFlash('success', 'Email permintaan kredensial berhasil dikirim ke ' . $student->getEmail() . '.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Gagal mengirim email: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_student_show', ['id' => $student->getId()]);
    }
}
