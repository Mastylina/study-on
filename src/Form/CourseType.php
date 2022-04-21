<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints;
class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'required' => true,
                'constraints' => [new Length([
                    'max' => 255,
                    'maxMessage' => 'Превышена максимальная длина символов'
                ])
                ],
            ])

            ->add('name' , TextType::class, [
                'required' => true,
                'constraints' => [new Length([
                    'max' => 255,
                    'maxMessage' => 'Превышена максимальная длина символов'
                    ])
                ],
            ])
            ->add('description', TextType::class, [
                'required' => false,
                'constraints' => [new Length([
                    'max' => 1000,
                    'maxMessage' => 'Превышена максимальная длина символов'
                ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
}