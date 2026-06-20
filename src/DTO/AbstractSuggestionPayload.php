<?php

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Shared payload shape for suggestion reports (fixed + floating). Subclasses add date fields:
 * fixed adds day/month, floating adds date.
 */
abstract readonly class AbstractSuggestionPayload
{
	public function __construct(
		#[SerializedName('user_id')]
		#[Assert\NotBlank]
		public string  $userId,

		#[Assert\NotBlank]
		public string  $name,

		public ?string $description = null,

		public ?string $country = null,

		public ?string $comment = null,

		public ?string $platform = null,

		#[SerializedName('real_device')]
		#[Assert\Length(max: 2000)]
		public ?string $realDevice = null,

		#[SerializedName('device_country')]
		public ?string $deviceCountry = null,
	)
	{
	}
}
