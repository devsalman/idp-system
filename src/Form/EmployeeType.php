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
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class EmployeeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullname', TextType::class, [
                'label' => 'Nama Lengkap',
                'empty_data' => '',
                'constraints' => [new NotBlank(message: 'Nama lengkap wajib diisi.')],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(message: 'Email wajib diisi.'),
                    new Email(message: 'Format email tidak valid.'),
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Peran',
                'choices' => [
                    'Dosen' => Employee::ROLE_DOSEN,
                    'Staf' => Employee::ROLE_STAFF,
                ],
                'empty_data' => '',
                'constraints' => [new NotBlank(message: 'Peran wajib diisi.')],
            ])
            ->add('nip', TextType::class, [
                'label' => 'NIP',
                'empty_data' => '',
                'constraints' => [new NotBlank(message: 'NIP wajib diisi.')],
            ])
            ->add('entryYear', IntegerType::class, [
                'label' => 'Tahun Masuk',
                'constraints' => [new NotBlank(message: 'Tahun masuk wajib diisi.')],
            ])
            ->add('unit', EntityType::class, [
                'label' => 'Unit Organisasi',
                'class' => OrgUnit::class,
                'choice_label' => fn (OrgUnit $unit) => $unit->getName().' ('.$unit->getCode().')',
                'placeholder' => '— Pilih unit —',
                'constraints' => [new NotBlank(message: 'Unit organisasi wajib diisi.')],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Aktif' => Employee::STATUS_ACTIVE,
                    'Mengundurkan Diri' => Employee::STATUS_RESIGNED,
                    'Diberhentikan' => Employee::STATUS_SUSPENDED,
                ],
                'empty_data' => '',
                'constraints' => [new NotBlank(message: 'Status wajib diisi.')],
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
