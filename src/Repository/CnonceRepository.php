<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cnonce;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cnonce>
 */
class CnonceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cnonce::class);
    }

    public function findOneByValue(string $value): ?Cnonce
    {
        return $this->findOneBy(['value' => $value]);
    }
}
