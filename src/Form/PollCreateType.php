<?php

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PollCreateType extends AbstractType
{
	#[Override]
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name', TextType::class)
			->add('question', TextareaType::class)
			->add('start', DateTimeType::class, ['widget' => 'single_text'])
			->add('end', DateTimeType::class, ['widget' => 'single_text'])
			->add('optionsText', TextareaType::class, ['mapped' => false, 'label' => 'Options (one per line)']);
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
