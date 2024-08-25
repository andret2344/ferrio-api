<?php

namespace App\Service;

use App\Entity\Ban;
use Doctrine\ORM\EntityManagerInterface;

readonly class BanService {
	private EntityManagerInterface $entityManager;

	public function __construct(EntityManagerInterface $entityManager) {
		$this->entityManager = $entityManager;
	}

	public function getBanInfo(string $uuid): Ban|null {
		return $this->entityManager->getRepository(Ban::class)
			->findOneBy(['uuid' => $uuid]);
	}
}
