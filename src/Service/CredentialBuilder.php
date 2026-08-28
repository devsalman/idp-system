<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Employee;
use App\Entity\Student;

class CredentialBuilder
{
    public const TYPE_EMPLOYEE = 'EmployeeCredential';
    public const TYPE_STUDENT = 'StudentCredential';

    public function __construct(
        private readonly KeyPairService $keyPairService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Employee|Student $subject, string $holderDid, array $holderJwk, string $configId): array
    {
        $now = new \DateTimeImmutable();

        $claims = [
            'iss' => $this->keyPairService->did(),
            'sub' => $holderDid,
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'exp' => $now->modify('+1 year')->getTimestamp(),
            'jti' => bin2hex(random_bytes(16)),
            'cnf' => ['jwk' => $holderJwk],
            'vc' => [
                '@context' => [
                    'https://www.w3.org/2018/credentials/v1',
                ],
                'type' => ['VerifiableCredential', $this->credentialType($subject)],
                'credentialSubject' => array_merge(
                    ['id' => $holderDid],
                    $this->claimsFor($subject),
                ),
            ],
        ];

        if ($subject instanceof Employee) {
            $claims['vc']['@context'][] = 'https://w3id.org/security/suites/jws-2020/v1';
        }

        return $claims;
    }

    private function subjectUrn(Employee|Student $subject): string
    {
        if ($subject instanceof Employee) {
            return 'urn:identitylab:employee:' . $subject->getId();
        }

        return 'urn:identitylab:student:' . $subject->getId();
    }

    private function credentialType(Employee|Student $subject): string
    {
        return $subject instanceof Employee ? self::TYPE_EMPLOYEE : self::TYPE_STUDENT;
    }

    /**
     * @return array<string, mixed>
     */
    private function claimsFor(Employee|Student $subject): array
    {
        $unit = $subject->getUnit()?->getName();

        $claims = [
            'email' => $subject->getEmail(),
            'fullname' => $subject->getFullname(),
            'entryYear' => $subject->getEntryYear(),
            'unit' => $unit,
            'status' => $subject->getStatus(),
        ];

        if ($subject instanceof Employee) {
            $claims['role'] = $subject->getRole();
            $claims['nip'] = $subject->getNip();
        } else {
            $claims['nim'] = $subject->getNim();
        }

        return $claims;
    }
}
