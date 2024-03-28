<?php

namespace App\Controller\v1;

use App\Service\HolidayService;
use App\Service\LoggingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/v1/holiday', name: 'v1_holiday_')]
class HolidayControllerV1 extends AbstractController {
	public function __construct(private readonly HolidayService $holidayService,
								private readonly LoggingService $loggingService) {
	}

	#[Route('/{language<^\S{2}$>}', name: 'get_all', methods: ['GET'])]
	public function getAll(Request $request, string $language): Response {
		$this->loggingService->route($request);
		$holidayDays = $this->holidayService->getHolidays($language);
		$response = new JsonResponse($holidayDays);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{language<^\S{2}$>}/day/{month<\d+>}/{day<\d+>}', name: 'get_holiday_day', methods: ['GET'])]
	public function getHolidayDay(Request $request, string $language, int $month, int $day): Response {
		$this->loggingService->route($request);
		$holidayDay = $this->holidayService->getHolidayDay($language, $day, $month);
		$response = new JsonResponse($holidayDay);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}
}
