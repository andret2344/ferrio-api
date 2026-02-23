<?php

namespace App\Controller\v3;

use App\Service\HolidayServiceV3;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/v3/holidays', name: 'v3_holidays_')]
class HolidayControllerV3 extends AbstractController
{
	public function __construct(private readonly HolidayServiceV3 $holidayService)
	{
	}

	#[Route('', name: 'get_all', methods: ['GET'])]
	public function getAll(Request $request): JsonResponse
	{
		if (!$request->query->has('lang')) {
			return new JsonResponse(['error' => 'Missing required query parameter: lang'], 400);
		}

		$language = strtolower($request->query->getString('lang'));
		$year = $request->query->getInt('year', (int)date('Y'));
		$day = $request->query->has('day') ? $request->query->getInt('day') : null;
		$month = $request->query->has('month') ? $request->query->getInt('month') : null;
		$country = $request->query->has('country') ? strtoupper($request->query->getString('country')) : null;
		$grouping = $request->query->getBoolean('grouping', false);

		$holidays = $this->holidayService->getHolidays($language, $year, $day, $month, $country);

		if ($grouping) {
			$holidays = $this->holidayService->groupByDay($holidays);
		}

		return new JsonResponse($holidays);
	}
}
