<?php

namespace App\Controller\v2;

use App\Entity\MissingHoliday;
use App\Repository\LanguageRepository;
use App\Repository\MissingHolidayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(['/missing', '/v2/missing'], name: 'v2_missing_')]
class MissingControllerV2 extends AbstractController {
	public function __construct(
		private readonly EntityManagerInterface   $entityManager,
		private readonly MissingHolidayRepository $missingHolidayRepository,
		private readonly LanguageRepository       $languageRepository) {
	}

	#[Route('/', name: 'get_all', methods: ['GET'])]
	public function getAll(): Response {
		return new JsonResponse($this->missingHolidayRepository->findAll());
	}

	#[Route('/{id<^\d+$>}', name: 'get_by_id', methods: ['GET'])]
	public function getById(int $id): Response {
		return new JsonResponse($this->missingHolidayRepository->findOneBy(['id' => $id]));
	}

	#[Route('/{uid<^\S+$>}', name: 'get_by_uid', methods: ['GET'])]
	public function getByUid(int $uid): Response {
		return new JsonResponse($this->missingHolidayRepository->findBy(['userId' => $uid]));
	}

	#[Route('/', name: 'post', methods: ['POST'])]
	public function postFixed(Request $request): Response {
		$data = json_decode($request->getContent(), true);
		$language = $this->languageRepository->findOneBy(['code' => $data['language']]);
		$userId = $data['user_id'] ?? null;
		$name = $data['name'] ?? null;
		$description = $data['description'] ?? null;
		$report = new MissingHoliday(null, $userId, $language, $name, $description);
		$this->entityManager->persist($report);
		$this->entityManager->flush();
		return new Response(null, 204);
	}
}
