<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FixedSuggestionDTO extends AbstractSuggestionPayload
{
	public function __construct(
		string         $userId,
		string         $name,

		#[Assert\NotNull]
		#[Assert\Range(min: 1, max: 31)]
		public int     $day,

		#[Assert\NotNull]
		#[Assert\Range(min: 1, max: 12)]
		public int     $month,

		?string        $description = null,
		?string        $country = null,
		?string        $comment = null,
		?DeviceDTO     $device = null,
	)
	{
		parent::__construct($userId, $name, $description, $country, $comment, $device);
	}
}
