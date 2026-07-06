<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Override;

#[ORM\Entity]
#[ORM\Table(name: 'floating_holiday_error')]
#[ORM\Index(name: 'idx_flhe_platform', columns: ['platform'])]
#[ORM\Index(name: 'idx_flhe_device_country', columns: ['device_country'])]
#[ORM\Index(name: 'idx_flhe_report_state', columns: ['report_state'])]
class FloatingHolidayError implements JsonSerializable
{
	use ReporterDeviceMetaTrait;

	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private(set) ?int $id;

	#[ORM\Column(nullable: false)]
	private(set) string $userId;

	#[ORM\ManyToOne(targetEntity: Language::class)]
	#[ORM\JoinColumn(name: 'language_code', referencedColumnName: 'code')]
	private(set) Language $language;

	#[ORM\ManyToOne(targetEntity: FloatingHolidayMetadata::class, inversedBy: 'reports')]
	#[ORM\JoinColumn(name: 'metadata_id', referencedColumnName: 'id')]
	public ?FloatingHolidayMetadata $metadata;

	#[ORM\Column(type: 'string', enumType: ReportType::class)]
	private(set) ReportType $reportType;

	#[ORM\Column(type: 'text', length: 65536, nullable: true)]
	private(set) ?string $description;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $datetime;

	#[ORM\Column(type: 'string', enumType: ReportState::class)]
	private(set) ReportState $reportState;

	#[ORM\Column(type: 'text', nullable: true)]
	private(set) ?string $comment = null;

	public function __construct(
		string                  $userId,
		Language                $language,
		FloatingHolidayMetadata $metadata,
		ReportType              $reportType,
		?string                 $additionalDescription,
		?string                 $comment = null,
		Platform                $platform = Platform::UNKNOWN,
		?string                 $realDevice = null,
		?Country                $deviceCountry = null,
		?string                 $osVersion = null,
		?string                 $appVersion = null)
	{
		$this->userId = $userId;
		$this->language = $language;
		$this->metadata = $metadata;
		$this->reportType = $reportType;
		$this->description = $additionalDescription;
		$this->datetime = new DateTimeImmutable();
		$this->reportState = ReportState::REPORTED;
		$this->comment = $comment;
		$this->platform = $platform;
		$this->realDevice = $realDevice;
		$this->deviceCountry = $deviceCountry;
		$this->osVersion = $osVersion;
		$this->appVersion = $appVersion;
	}

	#[Override]
	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'language_code' => $this->language->code,
			'metadata_id' => $this->metadata->id,
			'report_type' => $this->reportType,
			'description' => $this->description,
			'datetime' => $this->datetime->format('Y-m-d H:i:s'),
			'report_state' => $this->reportState,
			'comment' => $this->comment,
			'device' => $this->deviceMeta,
		];
	}
}
