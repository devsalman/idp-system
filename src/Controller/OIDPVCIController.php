<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Entity\Student;
use App\Repository\EmployeeRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;


final class OIDPVCIController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmployeeRepository $employeeRepository,
        private readonly StudentRepository $studentRepository,
    ) {
    }
    
    #[Route('/credential_offer/{token}', name: 'oidpvci_credential_offer', methods: ['GET'])]
    public function credentialOffer(Request $request, string $token): JsonResponse
    {
        $tokenHash = hash('sha256', $token);
        $subject = $this->employeeRepository->findOneBy(['tokenHash' => $tokenHash]);
        if (!$subject instanceof Employee) {
            $subject = $this->studentRepository->findOneBy(['tokenHash' => $tokenHash]);
            if (!$subject instanceof Student) {
                return $this->json(['success' => false, 'error' => 'token tidak valid'], $status = 400);
            }
        }

        if ($subject->getTokenStatus() === 'claimed') {
            return $this->json(['success' => false, 'error' => 'token sudah diklaim'], $status = 400);
        }

        $credentialConfigurationId = [];
        if ($subject instanceof Employee) array_push($credentialConfigurationId, 'NFEmployeeCredential');
        else array_push($credentialConfigurationId, 'NFStudentCredential');

        return $this->json([
            'credential_issuer' => $request->getSchemeAndHttpHost(),
            'credential_configuration_ids' => $credentialConfigurationId,
            'grants' => [
                "urn:ietf:params:oauth:grant-type:pre-authorized_code" => [
                    'pre-authorized_code' => '123456',
                ]
            ]
        ]);
    }
}
