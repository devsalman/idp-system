<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Entity\Student;
use App\Repository\CnonceRepository;
use App\Repository\EmployeeRepository;
use App\Repository\StudentRepository;
use App\Service\CredentialBuilder;
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

    private const CNONCE_TTL_SECONDS = 300;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmployeeRepository $employeeRepository,
        private readonly StudentRepository $studentRepository,
        private readonly CnonceRepository $cnonceRepository,
        private readonly CredentialBuilder $credentialBuilder,
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

    #[Route('/nonce', name: 'oidpvci_nonce', methods: ['POST'])]
    public function nonce(): JsonResponse
    {
        $now = new \DateTimeImmutable();

        $cnonce = new \App\Entity\Cnonce();
        $cnonce->setValue(bin2hex(random_bytes(32)));
        $cnonce->setCreatedAt($now);
        $cnonce->setExpiresAt($now->modify('+' . self::CNONCE_TTL_SECONDS . ' seconds'));

        $this->entityManager->persist($cnonce);
        $this->entityManager->flush();

        return $this->json([
            'c_nonce' => $cnonce->getValue(),
            'c_nonce_expires_in' => self::CNONCE_TTL_SECONDS,
        ])->setCache(['no_store' => true]);
    }

    #[Route('/credential', name: 'oidpvci_credential', methods: ['POST'])]
    public function credential(Request $request): JsonResponse
    {
        $authorization = $request->headers->get('Authorization', '');
        $bearer = preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) ? $matches[1] : null;

        if ($bearer === null) {
            return $this->json([
                'error' => 'invalid_token',
                'error_description' => 'Missing bearer access token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $claims = $this->keyPairService->verifyAccessToken($bearer);
        } catch (\RuntimeException $e) {
            return $this->json([
                'error' => 'invalid_token',
                'error_description' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json([
                'error' => 'invalid_credential_request',
                'error_description' => 'Invalid JSON body',
            ], Response::HTTP_BAD_REQUEST);
        }

        $configurationId = $payload['credential_configuration_id'] ?? null;
        $identifier = $payload['credential_identifier'] ?? null;

        if (($configurationId !== null) === ($identifier !== null)) {
            return $this->json([
                'error' => 'invalid_request',
                'error_description' => 'Use exactly one of credential_configuration_id or credential_identifier',
            ], Response::HTTP_BAD_REQUEST);
        }

        $expectedAud = $request->getSchemeAndHttpHost();
        $proofs = $payload['proofs']['jwt'] ?? null;

        if (!is_array($proofs) || $proofs === []) {
            return $this->json([
                'error' => 'invalid_proof',
                'error_description' => 'Missing proofs',
            ], Response::HTTP_BAD_REQUEST);
        }

        $proofJwt = is_string($proofs[0] ?? null) ? $proofs[0] : '';

        $cnonce = $this->lookupAndConsumeNonce($proofJwt);

        if ($cnonce === null) {
            return $this->json([
                'error' => 'invalid_proof',
                'error_description' => 'Invalid or expired nonce in proof',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $holderJwk = $this->keyPairService->verifyProofJwt($proofJwt, $expectedAud, $cnonce);
        } catch (\RuntimeException $e) {
            return $this->json([
                'error' => 'invalid_proof',
                'error_description' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $holderDid = 'did:jwk:' . $this->base64UrlEncode(json_encode($holderJwk, JSON_UNESCAPED_SLASHES));

        $entityType = $claims['entity_type'] ?? null;
        $entityId = $claims['entity_id'] ?? null;

        $subject = null;
        if ($entityType === 'employee') {
            $subject = $entityId !== null ? $this->employeeRepository->find($entityId) : null;
        } elseif ($entityType === 'student') {
            $subject = $entityId !== null ? $this->studentRepository->find($entityId) : null;
        }

        if (!$subject instanceof Employee && !$subject instanceof Student) {
            return $this->json([
                'error' => 'invalid_token',
                'error_description' => 'Subject not found',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $configId = is_string($configurationId) ? $configurationId : (is_string($identifier) ? $identifier : '');

        try {
            $vcClaims = $this->credentialBuilder->build($subject, $holderDid, $configId);
            $credential = $this->keyPairService->signCredential($vcClaims);
        } catch (\RuntimeException $e) {
            return $this->json([
                'error' => 'server_error',
                'error_description' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $subject->setTokenStatus(Employee::TOKEN_STATUS_CLAIMED);
        $subject->setDid($holderDid);
        $this->entityManager->persist($subject);
        $this->entityManager->flush();

        return $this->json([
            'credentials' => [
                ['credential' => $credential],
            ],
        ])->setCache(['no_store' => true]);
    }

    #[Route('/.well-known/openid-credential-issuer', name: 'oidpvci_metadata', methods: ['GET'])]
    public function credentialIssuerMetadata(Request $request): JsonResponse
    {
        $issuer = $request->getSchemeAndHttpHost();

        return $this->json([
            'credential_issuer' => $issuer,
            'token_endpoint' => $issuer . '/token',
            'credential_endpoint' => $issuer . '/credential',
            'nonce_endpoint' => $issuer . '/nonce',
            'credential_configurations_supported' => [
                'NFEmployeeCredential' => [
                    'format' => 'jwt_vc_json',
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
                    'format' => 'jwt_vc_json',
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

    private function lookupAndConsumeNonce(string $proofJwt): ?string
    {
        $parts = explode('.', $proofJwt);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (!is_array($payload) || !isset($payload['nonce']) || !is_string($payload['nonce'])) {
            return null;
        }

        $cnonce = $this->cnonceRepository->findOneByValue($payload['nonce']);

        if (!$cnonce instanceof \App\Entity\Cnonce) {
            return null;
        }

        if ($cnonce->getExpiresAt() < new \DateTimeImmutable()) {
            $this->entityManager->remove($cnonce);
            $this->entityManager->flush();

            return null;
        }

        $value = $cnonce->getValue();
        $this->entityManager->remove($cnonce);
        $this->entityManager->flush();

        return $value;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
