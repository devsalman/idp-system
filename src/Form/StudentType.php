<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\OrgUnit;
use App\Entity\Student;
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

class StudentType extends AbstractType
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
            ->add('nim', TextType::class, [
                'label' => 'NIM',
                'empty_data' => '',
                'constraints' => [new NotBlank(message: 'NIM wajib diisi.')],
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
                    'Aktif' => Student::STATUS_ACTIVE,
                    'Lulus' => Student::STATUS_GRADUATED,
                    'Diberhentikan' => Student::STATUS_SUSPENDED,
                ],
                'empty_data' => '',
                'constraints' => [new NotBlank(message: 'Status wajib diisi.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Student::class,
            'csrf_token_id' => 'student',
        ]);
    }
}
