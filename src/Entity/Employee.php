<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\EmployeeRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ORM\Table(name: 'employee')]
class Employee
{
    public const ROLE_DOSEN = 'dosen';
    public const ROLE_STAFF = 'staff';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_RESIGNED = 'resigned';
    public const STATUS_SUSPENDED = 'suspended';

    public const TOKEN_STATUS_SENT = 'sent';
    public const TOKEN_STATUS_CLAIMED = 'claimed';

    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $did = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'Email wajib diisi.')]
    #[Assert\Email(message: 'Format email tidak valid.')]
    private string $email;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Nama lengkap wajib diisi.')]
    private string $fullname;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: 'Peran wajib diisi.')]
    #[Assert\Choice(choices: [self::ROLE_DOSEN, self::ROLE_STAFF])]
    private ?string $role = null;

    #[ORM\Column(length: 32, unique: true)]
    #[Assert\NotBlank(message: 'NIP wajib diisi.')]
    private string $nip;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotBlank(message: 'Tahun masuk wajib diisi.')]
    private ?int $entryYear = null;

    #[ORM\ManyToOne(targetEntity: OrgUnit::class)]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotBlank(message: 'Unit organisasi wajib diisi.')]
    private ?OrgUnit $unit = null;

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    #[Assert\NotBlank(message: 'Status wajib diisi.')]
    #[Assert\Choice(choices: [self::STATUS_ACTIVE, self::STATUS_RESIGNED, self::STATUS_SUSPENDED])]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $tokenHash = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Choice(choices: [self::TOKEN_STATUS_SENT, self::TOKEN_STATUS_CLAIMED])]
    private ?string $tokenStatus = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDid(): ?string
    {
        return $this->did;
    }

    public function setDid(?string $did): static
    {
        $this->did = $did;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getFullname(): string
    {
        return $this->fullname;
    }

    public function setFullname(string $fullname): static
    {
        $this->fullname = $fullname;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getNip(): string
    {
        return $this->nip;
    }

    public function setNip(string $nip): static
    {
        $this->nip = $nip;

        return $this;
    }

    public function getEntryYear(): ?int
    {
        return $this->entryYear;
    }

    public function setEntryYear(?int $entryYear): static
    {
        $this->entryYear = $entryYear;

        return $this;
    }

    public function getUnit(): ?OrgUnit
    {
        return $this->unit;
    }

    public function setUnit(?OrgUnit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(?string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getTokenStatus(): ?string
    {
        return $this->tokenStatus;
    }

    public function setTokenStatus(?string $tokenStatus): static
    {
        $this->tokenStatus = $tokenStatus;

        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;

        return $this;
    }
}
