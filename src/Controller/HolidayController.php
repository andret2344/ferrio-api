<?php

namespace App\Controller;

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
		$response = new JsonResponse($this->holidayService->getHolidays($this->holidayService->getLanguage($lang)));
		$response->setEncodingOptions($response->getEncodingOptions() | JSON_PRETTY_PRINT);
		return $response;
	}

	#[Route('/{lang}/{id}', name: 'get_all', methods: ['GET'])]
	public function getOne(string $lang, int $id): Response {
		$response = new JsonResponse($this->holidayService->getHoliday($this->holidayService->getLanguage($lang), $id));
		$response->setEncodingOptions($response->getEncodingOptions() | JSON_PRETTY_PRINT);
		return $response;
	}
}
