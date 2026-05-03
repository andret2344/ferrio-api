<?php

namespace App\Controller\v2;

use App\Service\HolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/v2/holiday', name: 'v2_holiday_')]
class HolidayControllerV2 extends AbstractController
{
	public function __construct(private readonly HolidayService $holidayService)
	{
	}

	#[Route('/{language<^\S{2}$>}', name: 'get_all', methods: ['GET'])]
	public function getAll(string $language): JsonResponse
	{
		return new JsonResponse([
			'fixed' => $this->holidayService->getHolidays($language),
			'floating' => $this->holidayService->getFloatingHolidays($language),
		]);
	}

	#[Route('/{language<^\S{2}$>}/day/{month<\d+>}/{day<\d+>}', name: 'get_holiday_day', methods: ['GET'])]
	public function getHolidayDay(string $language, int $month, int $day): JsonResponse
	{
		return new JsonResponse($this->holidayService->getHolidayDay($language, $day, $month));
	}

	#[Route('/{language<^\S{2}$>}/floating', name: 'get_floating_holidays', methods: ['GET'])]
	public function getFloatingHolidays(string $language): JsonResponse
	{
		return new JsonResponse($this->holidayService->getFloatingHolidays($language));
	}

	#[Route('/{language<^\S{2}$>}/fixed', name: 'get_fixed_holidays', methods: ['GET'])]
	public function getFixedHolidays(string $language): JsonResponse
	{
		return new JsonResponse($this->holidayService->getHolidays($language));
	}
}
