<?php

namespace App\Controller;

use App\Entity\Holiday;
use App\Service\HolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/holiday', name: 'holiday_')]
class HolidayController extends AbstractController {
	private HolidayService $holidayService;

	public function __construct(HolidayService $holidayService) {
		$this->holidayService = $holidayService;
	}

	#[Route('/{lang}', name: 'get_all', methods: ['GET'])]
	public function getAll(string $lang): Response {
		/**
		 * @var Holiday[] $holidays
		 */
		$holidays = $this->holidayService->getHolidays($this->holidayService->getLanguage($lang));
		$response = new JsonResponse(json_encode($holidays));
		$response->headers->set('Content-Length', strlen(json_encode($holidays, JSON_PRETTY_PRINT)));
		return $response;
	}

	#[Route('/{lang}/{id}', name: 'get_one', methods: ['GET'])]
	public function getOne(string $lang, int $id): Response {
		/**
		 * @var Holiday $holiday
		 */
		$holiday = $this->holidayService->getHoliday($this->holidayService->getLanguage($lang), $id);
		$response = new JsonResponse($holiday);
		$response->setEncodingOptions($response->getEncodingOptions() | JSON_PRETTY_PRINT);
		$response->headers->set('Content-Length', strlen(json_encode($holiday, JSON_PRETTY_PRINT)));
		return $response;
	}
}
