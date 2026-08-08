<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Employee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employee>
 */
class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    /**
     * @return Employee[]
     */
    public function findAllOrderedByFullname(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.fullname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function countByRole(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.role', 'COUNT(e.id) AS total')
            ->groupBy('e.role')
            ->getQuery()
            ->getResult();

        return array_reduce($rows, fn (array $acc, array $row) => $acc + [$row['role'] => (int) $row['total']], []);
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.status', 'COUNT(e.id) AS total')
            ->groupBy('e.status')
            ->getQuery()
            ->getResult();

        return array_reduce($rows, fn (array $acc, array $row) => $acc + [$row['status'] => (int) $row['total']], []);
    }

    /**
     * @return array<string, int>
     */
    public function countByTokenStatus(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('COALESCE(e.tokenStatus, \'pending\') AS status', 'COUNT(e.id) AS total')
            ->groupBy('status')
            ->getQuery()
            ->getResult();

        return array_reduce($rows, fn (array $acc, array $row) => $acc + [$row['status'] => (int) $row['total']], []);
    }
}
