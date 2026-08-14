<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Employee;
use App\Entity\OrgUnit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmployeeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullname', TextType::class, [
                'label' => 'Nama Lengkap',
                'empty_data' => '',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'empty_data' => '',
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Peran',
                'choices' => [
                    'Dosen' => Employee::ROLE_DOSEN,
                    'Staf' => Employee::ROLE_STAFF,
                ],
            ])
            ->add('nip', TextType::class, [
                'label' => 'NIP',
                'empty_data' => '',
            ])
            ->add('entryYear', IntegerType::class, [
                'label' => 'Tahun Masuk',
            ])
            ->add('unit', EntityType::class, [
                'label' => 'Unit Organisasi',
                'class' => OrgUnit::class,
                'choice_label' => fn (OrgUnit $unit) => $unit->getName().' ('.$unit->getCode().')',
                'placeholder' => '— Pilih unit —',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Aktif' => Employee::STATUS_ACTIVE,
                    'Mengundurkan Diri' => Employee::STATUS_RESIGNED,
                    'Diberhentikan' => Employee::STATUS_SUSPENDED,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Employee::class,
            'csrf_token_id' => 'employee',
        ]);
    }
}
