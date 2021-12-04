<?php

namespace App\Controller;

use App\Entity\Holiday;
use App\Repository\HolidayRepository;
use App\Service\HolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/holiday', name: 'holiday_')]
class HolidayController extends AbstractController {
	private HolidayService $holidayService;
	private HolidayRepository $holidayRepository;

	public function __construct(HolidayService $holidayService, HolidayRepository $holidayRepository) {
		$this->holidayService = $holidayService;
		$this->holidayRepository = $holidayRepository;
	}

	#[Route('/{lang}', name: 'get_all', methods: ['GET'])]
	public function getAll(string $lang): Response {
		/**
		 * @var Holiday[] $holidays
		 */
		$holidays = $this->holidayRepository->findBy([
			'language' => $lang
		]);
		$response = new JsonResponse($holidays);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{lang}/{id}', name: 'get_one', methods: ['GET'])]
	public function getOne(string $lang, int $id): Response {
		/**
		 * @var Holiday $holiday
		 */
		$holiday = $this->holidayService->getHoliday($this->holidayService->getLanguage($lang), $id);
		$response = new JsonResponse($holiday);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}
}
