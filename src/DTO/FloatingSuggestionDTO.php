<?php

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class FloatingSuggestionDTO
{
	#[SerializedName('user_id')]
	#[Assert\NotBlank]
	public string $userId;

	#[Assert\NotBlank]
	public string $name;

	#[Assert\NotBlank]
	public string $date;

	public ?string $description;

	public ?string $country;

	public function __construct(
		string  $userId,
		string  $name,
		string  $date,
		?string $description = null,
		?string $country = null,
	)
	{
		$this->userId = $userId;
		$this->name = $name;
		$this->date = $date;
		$this->description = $description;
		$this->country = $country;
	}
}
