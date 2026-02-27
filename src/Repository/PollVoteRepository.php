<?php

namespace App\Repository;

use App\Entity\Poll;
use App\Entity\PollVote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PollVoteRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, PollVote::class);
	}

	public function findByUserAndPoll(string $userId, Poll $poll): ?PollVote
	{
		return $this->findOneBy(['userId' => $userId, 'poll' => $poll]);
	}

	/**
	 * Returns [optionId => count] map for a given poll.
	 *
	 * @return array<int, int>
	 */
	public function countByPoll(Poll $poll): array
	{
		$rows = $this->createQueryBuilder('v')
			->select('IDENTITY(v.option) AS optionId, COUNT(v.id) AS cnt')
			->where('v.poll = :poll')
			->setParameter('poll', $poll)
			->groupBy('v.option')
			->getQuery()
			->getResult();

		$map = [];
		foreach ($rows as $row) {
			$map[(int)$row['optionId']] = (int)$row['cnt'];
		}
		return $map;
	}
}
