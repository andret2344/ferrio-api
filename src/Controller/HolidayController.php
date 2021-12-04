<?php

namespace App\Controller;

use App\Entity\Holiday;
use App\Repository\LanguageRepository;
use App\Service\HolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/holiday', name: 'holiday_')]
class HolidayController extends AbstractController {
	private HolidayService $holidayService;
	private LanguageRepository $languageRepository;

	public function __construct(HolidayService $holidayService, LanguageRepository $languageRepository) {
		$this->holidayService = $holidayService;
		$this->languageRepository = $languageRepository;
	}

	#[Route('/{lang}', name: 'get_all', methods: ['GET'])]
	public function getAll(string $lang): Response {
		/**
		 * @var Holiday[] $holidays
		 */
		$holidays = $this->holidayService->getHolidays($this->languageRepository->findOneBy(['code' => $lang]));
		$response = new JsonResponse($holidays);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{lang}/{id}', name: 'get_one', methods: ['GET'])]
	public function getOne(string $lang, int $id): Response {
		/**
		 * @var Holiday $holiday
		 */
		$holiday = $this->holidayService->getHoliday($this->languageRepository->findOneBy(['code' => $lang]), $id);
		$response = new JsonResponse($holiday);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}
}
