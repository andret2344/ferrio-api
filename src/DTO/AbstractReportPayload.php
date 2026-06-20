<?php

namespace App\DTO;

use App\Entity\ReportType;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Shared payload shape for error reports (fixed + floating). Subclasses differ in name only,
 * which is what Symfony's MapRequestPayload uses to route /v2/report/fixed vs /v2/report/floating.
 */
abstract readonly class AbstractReportPayload
{
	public function __construct(
		#[SerializedName('user_id')]
		#[Assert\NotBlank]
		public string  $userId,

		#[Assert\NotBlank]
		public string  $language,

		#[Assert\NotNull]
		public int     $metadata,

		#[SerializedName('report_type')]
		#[Assert\NotBlank]
		#[Assert\Choice(callback: [ReportType::class, 'values'])]
		public string  $reportType,

		public ?string $description = null,

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
