<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Employee;
use App\Entity\Student;
use App\Repository\EmployeeRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ClaimController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmployeeRepository $employeeRepository,
        private readonly StudentRepository $studentRepository,
    ) {
    }

    #[Route('/claim/{token}', name: 'app_claim', methods: ['GET'])]
    public function claim(string $token): Response
    {
        $tokenHash = hash('sha256', $token);

        $employee = $this->employeeRepository->findOneBy(['tokenHash' => $tokenHash]);

        if ($employee instanceof Employee) {
            if ($employee->getTokenStatus() === Employee::TOKEN_STATUS_CLAIMED) {
                return $this->render('claim/confirm.html.twig', [
                    'status' => 'already_claimed',
                    'name' => $employee->getFullname(),
                ]);
            }

            if ($employee->getTokenExpiresAt() !== null && $employee->getTokenExpiresAt() < new \DateTimeImmutable()) {
                return $this->render('claim/confirm.html.twig', [
                    'status' => 'expired',
                    'name' => $employee->getFullname(),
                ]);
            }

            return $this->render('claim/confirm.html.twig', [
                'status' => 'pending',
                'name' => $employee->getFullname(),
                'entity_type' => 'pegawai',
                'token' => $token,
            ]);
        }

        $student = $this->studentRepository->findOneBy(['tokenHash' => $tokenHash]);

        if ($student instanceof Student) {
            if ($student->getTokenStatus() === Student::TOKEN_STATUS_CLAIMED) {
                return $this->render('claim/confirm.html.twig', [
                    'status' => 'already_claimed',
                    'name' => $student->getFullname(),
                ]);
            }

            if ($student->getTokenExpiresAt() !== null && $student->getTokenExpiresAt() < new \DateTimeImmutable()) {
                return $this->render('claim/confirm.html.twig', [
                    'status' => 'expired',
                    'name' => $student->getFullname(),
                ]);
            }

            return $this->render('claim/confirm.html.twig', [
                'status' => 'pending',
                'name' => $student->getFullname(),
                'entity_type' => 'mahasiswa',
                'token' => $token,
            ]);
        }

        throw $this->createNotFoundException('Tautan klaim tidak valid atau sudah tidak berlaku.');
    }

    #[Route('/claim/{token}/confirm', name: 'app_claim_confirm', methods: ['POST'])]
    public function confirmClaim(string $token): Response
    {
        $tokenHash = hash('sha256', $token);

        $employee = $this->employeeRepository->findOneBy(['tokenHash' => $tokenHash]);

        if ($employee instanceof Employee) {
            if ($employee->getTokenStatus() === Employee::TOKEN_STATUS_CLAIMED) {
                $this->addFlash('error', 'Kredensial sudah pernah diklaim.');

                return $this->redirectToRoute('app_claim', ['token' => $token]);
            }

            if ($employee->getTokenExpiresAt() !== null && $employee->getTokenExpiresAt() < new \DateTimeImmutable()) {
                $this->addFlash('error', 'Tautan klaim sudah kedaluwarsa.');

                return $this->redirectToRoute('app_claim', ['token' => $token]);
            }

            $employee->setTokenStatus(Employee::TOKEN_STATUS_CLAIMED);
            $this->entityManager->flush();

            return $this->render('claim/confirm.html.twig', [
                'status' => 'success',
                'name' => $employee->getFullname(),
            ]);
        }

        $student = $this->studentRepository->findOneBy(['tokenHash' => $tokenHash]);

        if ($student instanceof Student) {
            if ($student->getTokenStatus() === Student::TOKEN_STATUS_CLAIMED) {
                $this->addFlash('error', 'Kredensial sudah pernah diklaim.');

                return $this->redirectToRoute('app_claim', ['token' => $token]);
            }

            if ($student->getTokenExpiresAt() !== null && $student->getTokenExpiresAt() < new \DateTimeImmutable()) {
                $this->addFlash('error', 'Tautan klaim sudah kedaluwarsa.');

                return $this->redirectToRoute('app_claim', ['token' => $token]);
            }

            $student->setTokenStatus(Student::TOKEN_STATUS_CLAIMED);
            $this->entityManager->flush();

            return $this->render('claim/confirm.html.twig', [
                'status' => 'success',
                'name' => $student->getFullname(),
            ]);
        }

        throw $this->createNotFoundException('Tautan klaim tidak valid.');
    }
}
