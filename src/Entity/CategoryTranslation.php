<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'category_translation')]
class CategoryTranslation
{
	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'translations')]
	#[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
	private(set) Category $category;

	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[ORM\JoinColumn(name: 'language_code', referencedColumnName: 'code', onDelete: 'CASCADE')]
	private(set) Language $language;

	#[ORM\Column(type: 'string', length: 127)]
	public string $name;

	public function __construct(Category $category, Language $language, string $name)
	{
		$this->category = $category;
		$this->language = $language;
		$this->name = $name;
	}
}
