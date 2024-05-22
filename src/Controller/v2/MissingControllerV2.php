<?php

namespace App\Controller\v2;

use App\Entity\MissingFixedHoliday;
use App\Entity\MissingFloatingHoliday;
use App\Repository\MissingFixedHolidayRepository;
use App\Repository\MissingFloatingHolidayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(['/missing', '/v2/missing'], name: 'v2_missing_')]
class MissingControllerV2 extends AbstractController {
	public function __construct(
		private readonly EntityManagerInterface           $entityManager,
		private readonly MissingFixedHolidayRepository    $missingFixedHolidayRepository,
		private readonly MissingFloatingHolidayRepository $missingFloatingHolidayRepository) {
	}

	#[Route('/', name: 'get_all', methods: ['GET'])]
	public function getAll(): Response {
		return new JsonResponse([
			'fixed' => $this->missingFixedHolidayRepository->findAll(),
			'floating' => $this->missingFloatingHolidayRepository->findAll()
		]);
	}

	#[Route('/{uid<^\S+$>}', name: 'get_by_uid', methods: ['GET'])]
	public function getByUid(string $uid): Response {
		return new JsonResponse([
			'fixed' => $this->missingFixedHolidayRepository->findBy(['userId' => $uid]),
			'floating' => $this->missingFloatingHolidayRepository->findBy(['userId' => $uid])
		]);
	}

	#[Route('/fixed', name: 'post_fixed', methods: ['POST'])]
	public function postFixed(Request $request): Response {
		$data = json_decode($request->getContent(), true);
		$userId = $data['user_id'] ?? null;
		$name = $data['name'] ?? null;
		$day = $data['day'] ?? null;
		$month = $data['month'] ?? null;
		$description = $data['description'] ?? null;
		$report = new MissingFixedHoliday(null, $userId, $name, $description, $day, $month);
		$this->entityManager->persist($report);
		$this->entityManager->flush();
		return new Response(null, 204);
	}

	#[Route('/floating', name: 'post_floating', methods: ['POST'])]
	public function postFloating(Request $request): Response {
		$data = json_decode($request->getContent(), true);
		$userId = $data['user_id'] ?? null;
		$name = $data['name'] ?? null;
		$date = $data['date'] ?? null;
		$description = $data['description'] ?? null;
		$report = new MissingFloatingHoliday(null, $userId, $name, $description, $date);
		$this->entityManager->persist($report);
		$this->entityManager->flush();
		return new Response(null, 204);
	}
}
