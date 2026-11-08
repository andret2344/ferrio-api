<?php

namespace App\Entity;

use App\Enum\BlacklistReason;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A holiday name the admin rejected on the import page. Later pastes skip every name the matcher
 * considers the same - a reworded or re-declined variant of a rejected name stays rejected - and
 * report how many they skipped, so nothing disappears silently.
 *
 * Polish names only, like the import itself: the other languages are translations, not sources.
 */
#[ORM\Entity]
#[ORM\Table(name: 'holiday_blacklist')]
class HolidayBlacklistEntry
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'integer')]
	private(set) int $id;

	#[ORM\Column(length: 255)]
	private(set) string $name;

	/** @var array<string, int> the dates the sources gave when it was rejected, for the record */
	#[ORM\Column(type: 'json')]
	private(set) array $dates;

	#[ORM\Column(length: 16, enumType: BlacklistReason::class)]
	private(set) BlacklistReason $reason;

	#[ORM\Column(length: 1023, nullable: true)]
	private(set) ?string $comment;

	#[ORM\Column(type: 'datetimetz_immutable')]
	private(set) DateTimeImmutable $createdAt;

	/**
	 * @param array<string, int> $dates
	 */
	public function __construct(string $name, array $dates, BlacklistReason $reason, ?string $comment, DateTimeImmutable $createdAt)
	{
		$this->name = $name;
		$this->dates = $dates;
		$this->reason = $reason;
		$this->comment = $comment;
		$this->createdAt = $createdAt;
	}
}
