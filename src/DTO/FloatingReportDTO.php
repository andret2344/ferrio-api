<?php

namespace App\DTO;

use App\Entity\ReportType;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class FloatingReportDTO
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

	public ?string $comment;

	public function __construct(
		string  $userId,
		string  $language,
		int     $metadata,
		string  $reportType,
		?string $description = null,
		?string $comment = null,
	)
	{
		$this->userId = $userId;
		$this->language = $language;
		$this->metadata = $metadata;
		$this->reportType = $reportType;
		$this->description = $description;
		$this->comment = $comment;
	}

	public static function getValidReportTypes(): array
	{
		return array_column(ReportType::cases(), 'value');
	}
}
