<?php

namespace App\Repository;

use App\Entity\AdminUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<AdminUser>
 */
class AdminUserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, AdminUser::class);
	}

	/**
	 * Resolves the single login field against both columns, which is what lets an admin sign in with
	 * either their e-mail or their username.
	 */
	#[Override]
	public function loadUserByIdentifier(string $identifier): ?UserInterface
	{
		return $this->findOneByIdentifier($identifier);
	}

	/**
	 * The same lookup typed as the concrete entity, for callers (the console commands) that need the
	 * account's own properties rather than the `UserInterface` contract.
	 */
	public function findOneByIdentifier(string $identifier): ?AdminUser
	{
		return $this->createQueryBuilder('u')
			->where('u.email = :identifier')
			->orWhere('u.username = :identifier')
			->setParameter('identifier', $identifier)
			->getQuery()
			->getOneOrNullResult();
	}
}
