<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrgUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrgUnit>
 */
class OrgUnitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrgUnit::class);
    }

    /**
     * @return OrgUnit[]
     */
    public function findAllOrderedByCode(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
