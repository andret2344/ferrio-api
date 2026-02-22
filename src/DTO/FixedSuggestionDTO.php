<?php

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class FixedSuggestionDTO
{
	#[SerializedName('user_id')]
	#[Assert\NotBlank]
	public string $userId;

	#[Assert\NotBlank]
	public string $name;

	#[Assert\NotNull]
	#[Assert\Range(min: 1, max: 31)]
	public int $day;

	#[Assert\NotNull]
	#[Assert\Range(min: 1, max: 12)]
	public int $month;

	public ?string $description;

	public ?string $country;

	public function __construct(
		string  $userId,
		string  $name,
		int     $day,
		int     $month,
		?string $description = null,
		?string $country = null,
	)
	{
		$this->userId = $userId;
		$this->name = $name;
		$this->day = $day;
		$this->month = $month;
		$this->description = $description;
		$this->country = $country;
	}
}
