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

    #[Route('/.well-known/openid-credential-issuer', name: 'oidpvci_metadata', methods: ['GET'])]
    public function credentialIssuerMetadata(Request $request): JsonResponse
    {
        $issuer = $request->getSchemeAndHttpHost();

        return $this->json([
            'credential_issuer' => $issuer,
            'credential_endpoint' => $issuer . '/credential',
            'credential_configurations_supported' => [
                'NFEmployeeCredential' => [
                    'format' => 'vc+jwt',
                    'scope' => 'NFEmployeeCredential',
                    'cryptographic_binding_methods_supported' => ['jwk'],
                    'proof_types_supported' => [
                        'jwt' => [
                            'proof_signing_alg_values_supported' => ['ES256'],
                        ],
                    ],
                    'credential_metadata' => [
                        'claims' => [
                            ['path' => ['email']],
                            ['path' => ['fullname']],
                            ['path' => ['role']],
                            ['path' => ['nip']],
                            ['path' => ['entryYear']],
                            ['path' => ['unit']],
                            ['path' => ['status']],
                        ],
                        'display' => [
                            [
                                'name' => 'Employee Identity Credential',
                                'locale' => 'en-US',
                                'description' => 'Verifiable employee identity credential issued by Identitylab',
                                'background_color' => '#0f766e',
                                'text_color' => '#ffffff',
                            ],
                        ],
                    ],
                ],
                'NFStudentCredential' => [
                    'format' => 'vc+jwt',
                    'scope' => 'NFStudentCredential',
                    'cryptographic_binding_methods_supported' => ['jwk'],
                    'proof_types_supported' => [
                        'jwt' => [
                            'proof_signing_alg_values_supported' => ['ES256'],
                        ],
                    ],
                    'credential_metadata' => [
                        'claims' => [
                            ['path' => ['email']],
                            ['path' => ['fullname']],
                            ['path' => ['nim']],
                            ['path' => ['entryYear']],
                            ['path' => ['unit']],
                            ['path' => ['status']],
                        ],
                        'display' => [
                            [
                                'name' => 'Student Identity Credential',
                                'locale' => 'en-US',
                                'description' => 'Verifiable student identity credential issued by Identitylab',
                                'background_color' => '#1e40af',
                                'text_color' => '#ffffff',
                            ],
                        ],
                    ],
                ],
            ],
            'display' => [
                [
                    'locale' => 'en-US',
                    'name' => 'Identitylab ID',
                ],
            ],
        ]);
    }
}
