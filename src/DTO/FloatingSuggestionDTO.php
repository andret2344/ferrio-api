<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FloatingSuggestionDTO extends AbstractSuggestionPayload
{
	public function __construct(
		string         $userId,
		string         $name,

		#[Assert\NotBlank]
		public string  $date,

		?string        $description = null,
		?string        $country = null,
		?string        $comment = null,
		?DeviceDTO     $device = null,
	)
	{
		parent::__construct($userId, $name, $description, $country, $comment, $device);
	}
}
