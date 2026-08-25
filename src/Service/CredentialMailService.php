<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Employee;
use App\Entity\Student;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class CredentialMailService
{
    private const TOKEN_LENGTH = 32;
    private const TOKEN_EXPIRY_DAYS = 30;
    private const SENDER_EMAIL = 'noreply@identitylab.id';
    private const SENDER_NAME = 'Identitylab ID';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $baseUrl,
    ) {
    }

    public function sendCredentialRequest(Employee|Student $entity): void
    {
        $rawToken = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = new \DateTimeImmutable('+' . self::TOKEN_EXPIRY_DAYS . ' days');

        $entity->setTokenHash($tokenHash);
        $entity->setTokenStatus(Employee::TOKEN_STATUS_SENT);
        $entity->setTokenExpiresAt($expiresAt);
        $this->entityManager->flush();

        $claimUrl = $this->baseUrl . '/claim/' . $rawToken;
        $qrCodeDataUri = $this->generateQrCode($claimUrl);
        $name = $entity->getFullname();
        $email = $entity->getEmail();

        $templatedEmail = (new TemplatedEmail())
            ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to($email)
            ->subject('Permintaan Kredensial SSI — Identitylab')
            ->htmlTemplate('email/credential_request.html.twig')
            ->context([
                'name' => $name,
                'claim_url' => $claimUrl,
                'qr_code' => $qrCodeDataUri,
                'expires_at' => $expiresAt->format('d-m-Y'),
            ]);

        $this->mailer->send($templatedEmail);
    }

    private function generateQrCode(string $data): string
    {
        return "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={$data}";
    }
}
