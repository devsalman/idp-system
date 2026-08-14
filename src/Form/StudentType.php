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

class StudentType extends AbstractType
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
            ->add('nim', TextType::class, [
                'label' => 'NIM',
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
                    'Aktif' => Student::STATUS_ACTIVE,
                    'Lulus' => Student::STATUS_GRADUATED,
                    'Diberhentikan' => Student::STATUS_SUSPENDED,
                ],
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
