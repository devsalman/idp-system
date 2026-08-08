<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
#[ORM\Table(name: 'student')]
class Student
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_GRADUATED = 'graduated';
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
    private string $email;

    #[ORM\Column(length: 255)]
    private string $fullname;

    #[ORM\Column(length: 32, unique: true)]
    private string $nim;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $entryYear;

    #[ORM\ManyToOne(targetEntity: OrgUnit::class)]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id', nullable: false)]
    private OrgUnit $unit;

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    #[Assert\Choice(choices: [self::STATUS_ACTIVE, self::STATUS_GRADUATED, self::STATUS_SUSPENDED])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $tokenHash = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Choice(choices: [self::TOKEN_STATUS_SENT, self::TOKEN_STATUS_CLAIMED], allowNull: true)]
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

    public function getNim(): string
    {
        return $this->nim;
    }

    public function setNim(string $nim): static
    {
        $this->nim = $nim;

        return $this;
    }

    public function getEntryYear(): int
    {
        return $this->entryYear;
    }

    public function setEntryYear(int $entryYear): static
    {
        $this->entryYear = $entryYear;

        return $this;
    }

    public function getUnit(): OrgUnit
    {
        return $this->unit;
    }

    public function setUnit(OrgUnit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
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
