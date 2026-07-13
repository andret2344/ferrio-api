<?php

namespace App\Service;

use App\Entity\PollVote;
use App\Entity\ReportState;
use App\Enum\ReportKind;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Aggregates the reporting activity of end users (Firebase UIDs). There is no local user table —
 * a "user" only exists as the `user_id` column repeated across the four report tables, the poll
 * votes and the ban list, so every figure here is a GROUP BY over those tables.
 */
class AdminUserService
{
	public const string SCOPE_PENDING = 'pending';
	public const string SCOPE_ALL = 'all';

	private const int ACTIVE_DAYS = 30;

	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	/**
	 * One row per user that has ever reported anything, ordered by total reports descending.
	 *
	 * @return list<array{user_id: string, counts: array<string, int>, total: int, pending: int, last: ?DateTimeImmutable}>
	 */
	public function reporters(): array
	{
		$users = [];
		foreach (ReportKind::cases() as $kind) {
			$rows = $this->entityManager->createQueryBuilder()
				->select('r.userId AS userId', 'COUNT(r.id) AS cnt', 'MAX(r.datetime) AS last')
				->from($kind->entityClass(), 'r')
				->groupBy('r.userId')
				->getQuery()
				->getResult();
			foreach ($rows as $row) {
				$userId = (string)$row['userId'];
				$users[$userId] ??= [
					'user_id' => $userId,
					'counts' => array_fill_keys(array_column(ReportKind::cases(), 'value'), 0),
					'total' => 0,
					'pending' => 0,
					'last' => null,
				];
				$users[$userId]['counts'][$kind->value] += (int)$row['cnt'];
				$users[$userId]['total'] += (int)$row['cnt'];
				$last = $this->toDateTime($row['last']);
				if ($last !== null && ($users[$userId]['last'] === null || $last > $users[$userId]['last'])) {
					$users[$userId]['last'] = $last;
				}
			}

			$pendingRows = $this->entityManager->createQueryBuilder()
				->select('r.userId AS userId', 'COUNT(r.id) AS cnt')
				->from($kind->entityClass(), 'r')
				->where('r.reportState = :reported')
				->setParameter('reported', ReportState::REPORTED)
				->groupBy('r.userId')
				->getQuery()
				->getResult();
			foreach ($pendingRows as $row) {
				$userId = (string)$row['userId'];
				if (isset($users[$userId])) {
					$users[$userId]['pending'] += (int)$row['cnt'];
				}
			}
		}

		$rows = array_values($users);
		usort($rows, fn(array $a, array $b) => $b['total'] <=> $a['total']);
		return $rows;
	}

	/**
	 * @param list<array{user_id: string, total: int, pending: int, last: ?DateTimeImmutable}> $reporters
	 *
	 * @return array{reporters: int, active: int, reports: int, pending: int, votes: int, banned: int}
	 */
	public function totals(array $reporters, int $bannedCount): array
	{
		$activeSince = new DateTimeImmutable(sprintf('-%d days', self::ACTIVE_DAYS));
		$active = 0;
		$reports = 0;
		$pending = 0;
		foreach ($reporters as $row) {
			$reports += $row['total'];
			$pending += $row['pending'];
			if ($row['last'] !== null && $row['last'] >= $activeSince) {
				$active++;
			}
		}

		return [
			'reporters' => count($reporters),
			'active' => $active,
			'reports' => $reports,
			'pending' => $pending,
			'votes' => $this->entityManager->getRepository(PollVote::class)
				->count([]),
			'banned' => $bannedCount,
		];
	}

	/**
	 * @param string[] $userIds
	 *
	 * @return array<string, array{total: int, pending: int}> keyed by user id; users with no reports are absent
	 */
	public function reportCounts(array $userIds): array
	{
		$userIds = array_values(array_unique(array_filter($userIds)));
		if ($userIds === []) {
			return [];
		}

		$counts = [];
		foreach (ReportKind::cases() as $kind) {
			$rows = $this->entityManager->createQueryBuilder()
				->select('r.userId AS userId', 'COUNT(r.id) AS cnt', 'SUM(CASE WHEN r.reportState = :reported THEN 1 ELSE 0 END) AS pending')
				->from($kind->entityClass(), 'r')
				->where('r.userId IN (:userIds)')
				->setParameter('reported', ReportState::REPORTED)
				->setParameter('userIds', $userIds)
				->groupBy('r.userId')
				->getQuery()
				->getResult();
			foreach ($rows as $row) {
				$userId = (string)$row['userId'];
				$counts[$userId] ??= ['total' => 0, 'pending' => 0];
				$counts[$userId]['total'] += (int)$row['cnt'];
				$counts[$userId]['pending'] += (int)$row['pending'];
			}
		}
		return $counts;
	}

	/**
	 * Deletes every report of a user across the four report tables. `SCOPE_PENDING` limits the
	 * deletion to reports still in the REPORTED state, leaving already-moderated ones as an audit
	 * trail.
	 *
	 * @return int number of deleted reports
	 */
	public function deleteReports(string $userId, string $scope): int
	{
		$deleted = 0;
		$this->entityManager->wrapInTransaction(function () use ($userId, $scope, &$deleted): void {
			foreach (ReportKind::cases() as $kind) {
				$delete = $this->entityManager->createQueryBuilder()
					->delete($kind->entityClass(), 'r')
					->where('r.userId = :userId')
					->setParameter('userId', $userId);
				if ($scope === self::SCOPE_PENDING) {
					$delete->andWhere('r.reportState = :reported')
						->setParameter('reported', ReportState::REPORTED);
				}
				$deleted += (int)$delete->getQuery()
					->execute();
			}
		});
		return $deleted;
	}

	private function toDateTime(mixed $raw): ?DateTimeImmutable
	{
		if ($raw instanceof DateTimeImmutable) {
			return $raw;
		}
		if (!is_string($raw) || $raw === '') {
			return null;
		}
		return new DateTimeImmutable($raw);
	}
}
