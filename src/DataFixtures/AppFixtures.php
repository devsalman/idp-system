<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Employee;
use App\Entity\OrgUnit;
use App\Entity\Student;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $orgUnits = [
            ['code' => 'REK', 'name' => 'Rektorat', 'parent' => null],
            ['code' => 'REK.BUK', 'name' => 'Biro Umum & Keuangan', 'parent' => 'REK'],
            ['code' => 'REK.BAK', 'name' => 'Biro Akademik & Kemahasiswaan', 'parent' => 'REK'],
            ['code' => 'FT', 'name' => 'Fakultas Teknik', 'parent' => null],
            ['code' => 'FT.IF', 'name' => 'Prodi Teknik Informatika', 'parent' => 'FT'],
            ['code' => 'FT.TS', 'name' => 'Prodi Teknik Sipil', 'parent' => 'FT'],
            ['code' => 'UPT.PER', 'name' => 'UPT Perpustakaan', 'parent' => null],
        ];

        foreach ($orgUnits as $data) {
            $unit = new OrgUnit();
            $unit->setCode($data['code']);
            $unit->setName($data['name']);
            if (null !== $data['parent']) {
                $unit->setParent($this->getReference('org.'.$data['parent'], OrgUnit::class));
            }
            $manager->persist($unit);
            $this->addReference('org.'.$data['code'], $unit);
        }

        $students = [
            ['name' => 'Salman Fadila', 'nim' => '2024-0001', 'year' => 2024, 'status' => Student::STATUS_ACTIVE, 'unit' => 'FT.IF', 'email' => 'salman.fadila@student.univ.ac.id'],
            ['name' => 'Aisyah Putri', 'nim' => '2023-0042', 'year' => 2023, 'status' => Student::STATUS_ACTIVE, 'unit' => 'FT.TS', 'email' => 'aisyah.putri@student.univ.ac.id'],
            ['name' => 'Budi Prasetyo', 'nim' => '2023-0017', 'year' => 2023, 'status' => Student::STATUS_ACTIVE, 'unit' => 'FT.IF', 'email' => 'budi.prasetyo@student.univ.ac.id'],
            ['name' => 'Dewi Lestari', 'nim' => '2020-0120', 'year' => 2020, 'status' => Student::STATUS_GRADUATED, 'unit' => 'FT.IF', 'email' => 'dewi.lestari@student.univ.ac.id'],
            ['name' => 'Eko Nugroho', 'nim' => '2022-0078', 'year' => 2022, 'status' => Student::STATUS_SUSPENDED, 'unit' => 'FT.TS', 'email' => 'eko.nugroho@student.univ.ac.id'],
        ];

        foreach ($students as $data) {
            $student = new Student();
            $student->setFullname($data['name']);
            $student->setNim($data['nim']);
            $student->setEntryYear($data['year']);
            $student->setStatus($data['status']);
            $student->setEmail($data['email']);
            $student->setUnit($this->getReference('org.'.$data['unit'], OrgUnit::class));
            $manager->persist($student);
        }

        $employees = [
            ['name' => 'Dr. Retno Wulandari', 'role' => Employee::ROLE_DOSEN, 'nip' => '198501012016042002', 'year' => 2016, 'status' => Employee::STATUS_ACTIVE, 'unit' => 'FT.IF', 'email' => 'retno.wulandari@univ.ac.id'],
            ['name' => 'Ir. Hendra Gunawan', 'role' => Employee::ROLE_DOSEN, 'nip' => '197803012003121001', 'year' => 2003, 'status' => Employee::STATUS_ACTIVE, 'unit' => 'FT.TS', 'email' => 'hendra.gunawan@univ.ac.id'],
            ['name' => 'Budi Santoso', 'role' => Employee::ROLE_STAFF, 'nip' => '198901012019051001', 'year' => 2019, 'status' => Employee::STATUS_ACTIVE, 'unit' => 'REK.BUK', 'email' => 'budi.santoso@univ.ac.id'],
            ['name' => 'Rina Marlina', 'role' => Employee::ROLE_STAFF, 'nip' => '199205012022032002', 'year' => 2022, 'status' => Employee::STATUS_RESIGNED, 'unit' => 'UPT.PER', 'email' => 'rina.marlina@univ.ac.id'],
        ];

        foreach ($employees as $data) {
            $employee = new Employee();
            $employee->setFullname($data['name']);
            $employee->setRole($data['role']);
            $employee->setNip($data['nip']);
            $employee->setEntryYear($data['year']);
            $employee->setStatus($data['status']);
            $employee->setEmail($data['email']);
            $employee->setUnit($this->getReference('org.'.$data['unit'], OrgUnit::class));
            $manager->persist($employee);
        }

        $manager->flush();
    }
}
