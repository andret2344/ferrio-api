<?php

namespace App\Controller\v2;

use App\Service\HolidayService;
use App\Service\LoggingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(['/holiday', '/v2/holiday'], name: 'v2_holiday_')]
class HolidayControllerV2 extends AbstractController {
	public function __construct(private readonly HolidayService $holidayService,
								private readonly LoggingService $loggingService) {
	}

	#[Route('/{language<^\S{2}$>}', name: 'get_all', methods: ['GET'])]
	public function getAll(Request $request, string $language): Response {
		$this->loggingService->route($request);
		$holidayDays = $this->holidayService->getHolidays($language);
		$floatingHolidays = $this->holidayService->getFloatingHolidays($language);
		$response = new JsonResponse([
			'fixed' => $holidayDays,
			'floating' => $floatingHolidays
		]);
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

	#[Route('/{language<^\S{2}$>}/floating', name: 'get_floating_holidays', methods: ['GET'])]
	public function getFloatingHolidays(Request $request, string $language): Response {
		$this->loggingService->route($request);
		$holidayDay = $this->holidayService->getFloatingHolidays($language);
		$response = new JsonResponse($holidayDay);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{language<^\S{2}$>}/fixed', name: 'get_fixed_holidays', methods: ['GET'])]
	public function getFixedHolidays(Request $request, string $language): Response {
		$this->loggingService->route($request);
		$holidayDay = $this->holidayService->getHolidays($language);
		$response = new JsonResponse($holidayDay);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}
}
