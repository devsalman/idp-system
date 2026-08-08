<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Student>
 */
class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Student::class);
    }

    /**
     * @return Student[]
     */
    public function findAllOrderedByFullname(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.fullname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.status', 'COUNT(s.id) AS total')
            ->groupBy('s.status')
            ->getQuery()
            ->getResult();

        return array_reduce($rows, fn (array $acc, array $row) => $acc + [$row['status'] => (int) $row['total']], []);
    }

    /**
     * @return array<string, int>
     */
    public function countByTokenStatus(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('COALESCE(s.tokenStatus, \'pending\') AS status', 'COUNT(s.id) AS total')
            ->groupBy('status')
            ->getQuery()
            ->getResult();

        return array_reduce($rows, fn (array $acc, array $row) => $acc + [$row['status'] => (int) $row['total']], []);
    }
}
