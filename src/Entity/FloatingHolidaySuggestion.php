<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Override;

#[ORM\Entity]
#[ORM\Table(name: 'floating_holiday_suggestion')]
#[ORM\Index(name: 'idx_flhs_platform', columns: ['platform'])]
#[ORM\Index(name: 'idx_flhs_device_country', columns: ['device_country'])]
#[ORM\Index(name: 'idx_flhs_report_state', columns: ['report_state'])]
class FloatingHolidaySuggestion implements JsonSerializable
{
	use ReporterDeviceMetaTrait;

	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private(set) ?int $id;

	#[ORM\Column(type: 'string')]
	private(set) string $userId;

	#[ORM\Column(type: 'string')]
	private(set) string $name;

	#[ORM\Column(type: 'text')]
	private(set) string $description;

	#[ORM\Column(type: 'text')]
	private(set) string $date;

	#[ORM\ManyToOne(targetEntity: Country::class)]
	#[ORM\JoinColumn(name: 'country', referencedColumnName: 'iso_code', nullable: true)]
	private(set) ?Country $country;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $datetime;

	#[ORM\Column(type: 'string', enumType: ReportState::class)]
	private(set) ReportState $reportState;

	#[ORM\Column(type: 'text', nullable: true)]
	private(set) ?string $comment = null;

	#[ORM\OneToOne(targetEntity: FloatingHolidayMetadata::class)]
	#[ORM\JoinColumn(name: 'holiday', referencedColumnName: 'id')]
	private(set) ?FloatingHolidayMetadata $holiday;

	public function __construct(
		string                   $userId,
		string                   $name,
		string                   $description,
		string                   $date,
		?Country                 $country = null,
		DateTimeImmutable        $datetime = new DateTimeImmutable(),
		ReportState              $reportState = ReportState::REPORTED,
		?FloatingHolidayMetadata $floatingHolidayMetadata = null,
		?string                  $comment = null,
		Platform                 $platform = Platform::UNKNOWN,
		?string                  $realDevice = null,
		?Country                 $deviceCountry = null,
		?string                  $osVersion = null,
		?string                  $appVersion = null)
	{
		$this->userId = $userId;
		$this->name = $name;
		$this->description = $description;
		$this->date = $date;
		$this->country = $country;
		$this->datetime = $datetime;
		$this->reportState = $reportState;
		$this->holiday = $floatingHolidayMetadata;
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
			'name' => $this->name,
			'description' => $this->description,
			'date' => $this->date,
			'datetime' => $this->datetime->format('Y-m-d H:i:s'),
			'country' => $this->country?->isoCode,
			'report_state' => $this->reportState,
			'comment' => $this->comment,
			'holiday_id' => $this->holiday?->id,
			'device' => $this->deviceMeta,
		];
	}
}
