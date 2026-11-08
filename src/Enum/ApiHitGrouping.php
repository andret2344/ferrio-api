<?php

namespace App\Enum;

use DateTimeImmutable;

enum ApiHitGrouping: string
{
	case HOUR = 'hour';
	case DAY = 'day';
	case WEEK = 'week';
	case MONTH = 'month';

	/**
	 * Bucket label format applied to the row's UTC `bucket_hour`. Formats are chosen so that a
	 * plain string sort equals a chronological sort.
	 */
	public function format(): string
	{
		return match ($this) {
			self::HOUR => 'Y-m-d H:00',
			self::DAY => 'Y-m-d',
			self::WEEK => 'o-\WW',
			self::MONTH => 'Y-m',
		};
	}

	/**
	 * Start of the period that contains `$at`. Weeks are ISO weeks, starting on Monday, to match
	 * the `o-\WW` label.
	 */
	public function periodStart(DateTimeImmutable $at): DateTimeImmutable
	{
		return match ($this) {
			self::HOUR => $at->setTime((int)$at->format('G'), 0),
			self::DAY => $at->setTime(0, 0),
			self::WEEK => $at->setISODate((int)$at->format('o'), (int)$at->format('W'))->setTime(0, 0),
			self::MONTH => $at->modify('first day of this month')->setTime(0, 0),
		};
	}

	public function nextPeriodStart(DateTimeImmutable $periodStart): DateTimeImmutable
	{
		return $periodStart->modify(match ($this) {
			self::HOUR => '+1 hour',
			self::DAY => '+1 day',
			self::WEEK => '+1 week',
			self::MONTH => '+1 month',
		});
	}

	/**
	 * Start of the first period that lies wholly at or after `$from` - `$from` itself when it falls
	 * exactly on a period boundary, the next boundary otherwise.
	 */
	public function firstCompletePeriodStart(DateTimeImmutable $from): DateTimeImmutable
	{
		$start = $this->periodStart($from);
		if ($start < $from) {
			return $this->nextPeriodStart($start);
		}
		return $start;
	}

	/**
	 * Whether at least one whole period fits between `$from` (when the data starts) and `$now`.
	 */
	public function hasCompletePeriod(DateTimeImmutable $from, DateTimeImmutable $now): bool
	{
		return $this->nextPeriodStart($this->firstCompletePeriodStart($from)) <= $now;
	}

	/**
	 * This grouping, or the coarsest finer one when this one is not among `$available` (the
	 * groupings whose window holds a whole period). Shortening the window keeps the current
	 * `?group=` in the form, so "by month" over the last 7 days is reachable without picking it,
	 * and it would draw an empty chart.
	 *
	 * @param list<self> $available
	 */
	public function orFinerWithin(array $available): self
	{
		$cases = self::cases();
		$finerFirst = array_reverse(array_slice($cases, 0, array_search($this, $cases, true) + 1));
		foreach ($finerFirst as $grouping) {
			if (in_array($grouping, $available, true)) {
				return $grouping;
			}
		}
		return $this;
	}
}
