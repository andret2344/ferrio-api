<?php

namespace App\Service;

use App\Entity\Ban;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class BanService
{
	private EntityManagerInterface $entityManager;

	public function __construct(EntityManagerInterface $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	public function getBanInfo(string $userId): Ban|null
	{
		return $this->entityManager->getRepository(Ban::class)
			->findOneBy(['userId' => $userId]);
	}

	/**
	 * @return Ban[] newest ban first
	 */
	public function findAll(): array
	{
		return $this->entityManager->getRepository(Ban::class)
			->findBy([], ['datetime' => 'DESC']);
	}

	/**
	 * @param string[] $userIds
	 *
	 * @return array<string, Ban> keyed by user id; only banned users are present
	 */
	public function getBans(array $userIds): array
	{
		$userIds = array_values(array_unique(array_filter($userIds)));
		if ($userIds === []) {
			return [];
		}
		$bans = $this->entityManager->getRepository(Ban::class)
			->findBy(['userId' => $userIds]);
		$result = [];
		foreach ($bans as $ban) {
			$result[$ban->userId] = $ban;
		}
		return $result;
	}

	public function count(): int
	{
		return $this->entityManager->getRepository(Ban::class)
			->count([]);
	}

	/**
	 * Bans a user, or overwrites the reason of an existing ban. Bans are permanent — unbanning is
	 * deleting the row.
	 */
	public function ban(string $userId, string $reason): Ban
	{
		$ban = $this->getBanInfo($userId);
		if ($ban === null) {
			$ban = new Ban($userId, $reason, new DateTimeImmutable());
			$this->entityManager->persist($ban);
		} else {
			$ban->update($reason, new DateTimeImmutable());
		}
		$this->entityManager->flush();
		return $ban;
	}

	public function unban(string $userId): bool
	{
		$ban = $this->getBanInfo($userId);
		if ($ban === null) {
			return false;
		}
		$this->entityManager->remove($ban);
		$this->entityManager->flush();
		return true;
	}
}
