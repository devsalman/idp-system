<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Student;
use App\Form\StudentType;
use App\Repository\StudentRepository;
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
}
