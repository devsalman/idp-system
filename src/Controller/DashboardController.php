<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\EmployeeRepository;
use App\Repository\OrgUnitRepository;
use App\Repository\StudentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        StudentRepository $studentRepository,
        EmployeeRepository $employeeRepository,
        OrgUnitRepository $orgUnitRepository,
    ): Response {
        $studentTokenStatus = $studentRepository->countByTokenStatus();
        $employeeTokenStatus = $employeeRepository->countByTokenStatus();

        return $this->render('dashboard/index.html.twig', [
            'students_total' => $studentRepository->countAll(),
            'students_by_status' => $studentRepository->countByStatus(),
            'employees_total' => $employeeRepository->countAll(),
            'employees_by_role' => $employeeRepository->countByRole(),
            'employees_by_status' => $employeeRepository->countByStatus(),
            'units_total' => $orgUnitRepository->countAll(),
            'vc_claimed' => ($studentTokenStatus['claimed'] ?? 0) + ($employeeTokenStatus['claimed'] ?? 0),
            'vc_sent' => ($studentTokenStatus['sent'] ?? 0) + ($employeeTokenStatus['sent'] ?? 0),
        ]);
    }
}
