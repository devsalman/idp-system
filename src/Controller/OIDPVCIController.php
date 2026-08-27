<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Entity\Student;
use App\Repository\EmployeeRepository;
use App\Repository\StudentRepository;
use App\Service\KeyPairService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


final class OIDPVCIController extends AbstractController
{
    private const GRANT_PRE_AUTHORIZED_CODE = 'urn:ietf:params:oauth:grant-type:pre-authorized_code';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmployeeRepository $employeeRepository,
        private readonly StudentRepository $studentRepository,
        private readonly KeyPairService $keyPairService,
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
                    'pre-authorized_code' => $token,
                ]
            ]
        ]);
    }

    #[Route('/token', name: 'oidpvci_token', methods: ['POST'])]
    public function token(Request $request): JsonResponse
    {
        $grantType = (string) $request->request->get('grant_type', '');

        if ($grantType !== self::GRANT_PRE_AUTHORIZED_CODE) {
            return $this->json(['error' => 'unsupported_grant_type'], Response::HTTP_BAD_REQUEST);
        }

        $preAuthorizedCode = (string) $request->request->get('pre-authorized_code', '');

        if ($preAuthorizedCode === '') {
            return $this->json([
                'error' => 'invalid_request',
                'error_description' => 'Missing pre-authorized_code',
            ], Response::HTTP_BAD_REQUEST);
        }

        $tokenHash = hash('sha256', $preAuthorizedCode);

        $subject = $this->employeeRepository->findOneBy(['tokenHash' => $tokenHash]);
        if (!$subject instanceof Employee) {
            $subject = $this->studentRepository->findOneBy(['tokenHash' => $tokenHash]);
        }

        if (!$subject instanceof Employee && !$subject instanceof Student) {
            return $this->json([
                'error' => 'invalid_grant',
                'error_description' => 'Invalid pre-authorized_code',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($subject->getTokenStatus() === 'claimed') {
            return $this->json([
                'error' => 'invalid_grant',
                'error_description' => 'Credential already claimed',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($subject->getTokenExpiresAt() !== null && $subject->getTokenExpiresAt() < new \DateTimeImmutable()) {
            return $this->json([
                'error' => 'invalid_grant',
                'error_description' => 'Pre-authorized_code has expired',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($subject instanceof Employee) {
            $credentialConfigurationId = 'NFEmployeeCredential';
            $entityType = 'employee';
        } else {
            $credentialConfigurationId = 'NFStudentCredential';
            $entityType = 'student';
        }

        $now = new \DateTimeImmutable();

        $claims = [
            'iss' => $this->keyPairService->did(),
            'sub' => 'urn:identitylab:' . $entityType . ':' . $subject->getId(),
            'aud' => $request->getSchemeAndHttpHost() . '/credential',
            'iat' => $now->getTimestamp(),
            'exp' => $now->modify('+1 hour')->getTimestamp(),
            'jti' => bin2hex(random_bytes(16)),
            'credential_configuration_id' => $credentialConfigurationId,
            'entity_type' => $entityType,
            'entity_id' => $subject->getId(),
        ];

        try {
            $accessToken = $this->keyPairService->signAccessToken($claims);
        } catch (\RuntimeException $e) {
            return $this->json([
                'error' => 'server_error',
                'error_description' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'authorization_details' => [
                [
                    'type' => 'openid_credential',
                    'credential_configuration_id' => $credentialConfigurationId,
                    'credential_identifiers' => [$credentialConfigurationId],
                ],
            ],
        ]);
    }

    #[Route('/.well-known/openid-credential-issuer', name: 'oidpvci_metadata', methods: ['GET'])]
    public function credentialIssuerMetadata(Request $request): JsonResponse
    {
        $issuer = $request->getSchemeAndHttpHost();

        return $this->json([
            'credential_issuer' => $issuer,
            'token_endpoint' => $issuer . '/token',
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
