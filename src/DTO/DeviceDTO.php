<?php

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Device metadata attached to reports and suggestions. Shared by both payload families so the
 * nested `device` object keeps an identical shape across every /v3 report/suggestion endpoint.
 * Columns on the report tables stay flat; handlers map this object onto them.
 */
final readonly class DeviceDTO
{
	public function __construct(
		public ?string $platform = null,

		#[Assert\Length(max: 2000)]
		public ?string $model = null,

		public ?string $country = null,

		#[SerializedName('os_version')]
		#[Assert\Length(max: 64)]
		public ?string $osVersion = null,

		#[SerializedName('app_version')]
		#[Assert\Length(max: 64)]
		public ?string $appVersion = null,
	)
	{
	}
}
