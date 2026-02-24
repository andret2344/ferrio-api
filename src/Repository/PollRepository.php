<?php

namespace App\Repository;

use App\Entity\Poll;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PollRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Poll::class);
	}

	/**
	 * Returns all polls where start <= NOW <= end.
	 *
	 * @return Poll[]
	 */
	public function findActive(): array
	{
		$now = new \DateTimeImmutable();
		return $this->createQueryBuilder('p')
			->where('p.start <= :now')
			->andWhere('p.end >= :now')
			->setParameter('now', $now)
			->getQuery()
			->getResult();
	}
}
