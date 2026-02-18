<?php

namespace App\DTO;

use App\Entity\ReportType;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class FixedReportDTO
{
	#[SerializedName('user_id')]
	#[Assert\NotBlank]
	public string $userId;

	#[Assert\NotBlank]
	public string $language;

	#[Assert\NotNull]
	public int $metadata;

	#[SerializedName('report_type')]
	#[Assert\NotBlank]
	#[Assert\Choice(callback: [self::class, 'getValidReportTypes'])]
	public string $reportType;

	public ?string $description;

	public function __construct(
		string  $userId,
		string  $language,
		int     $metadata,
		string  $reportType,
		?string $description = null,
	)
	{
		$this->userId = $userId;
		$this->language = $language;
		$this->metadata = $metadata;
		$this->reportType = $reportType;
		$this->description = $description;
	}

	public static function getValidReportTypes(): array
	{
		return array_column(ReportType::cases(), 'value');
	}
}
