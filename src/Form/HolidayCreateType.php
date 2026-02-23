<?php

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HolidayCreateType extends AbstractType
{
	#[Override]
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('month', IntegerType::class)
			->add('day', IntegerType::class)
			->add('name', TextareaType::class)
			->add('description', TextareaType::class, ['required' => false])
			->add('country', TextType::class, ['required' => false])
			->add('mature', CheckboxType::class, ['required' => false]);
	}

	#[Override]
	public function getBlockPrefix(): string
	{
		return '';
	}

	#[Override]
	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'csrf_protection' => true,
			'allow_extra_fields' => true,
		]);
	}
}
