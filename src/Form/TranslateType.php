<?php

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TranslateType extends AbstractType
{
	#[Override]
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('metadata_id', HiddenType::class)
			->add('name', TextType::class, ['required' => false])
			->add('description', TextareaType::class, ['required' => false]);
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
