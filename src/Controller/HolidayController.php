<?php

namespace App\Controller;

use App\Entity\HolidayDay;
use App\Service\HolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/holiday', name: 'holiday_')]
class HolidayController extends AbstractController {
	public function __construct(private readonly HolidayService $holidayService) {
	}

	#[Route('/{language<^\S{2}$>}', name: 'get_all', methods: ['GET'])]
	public function getAll(string $language): Response {
		/**
		 * @var HolidayDay[] $holidayDays
		 */
		$holidayDays = $this->holidayService->getHolidays($language);
		$response = new JsonResponse($holidayDays);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{language<^\S{2}$>}/today', name: 'get_today', methods: ['GET'])]
	public function getTodayHolidays(string $language): Response {
		$holidayDay = $this->holidayService->getTodayHoliday($language);
		$response = new JsonResponse($holidayDay);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{language<^\S{2}$>}/day/{month<\d+>}/{day<\d+>}', name: 'get_holiday_day', methods: ['GET'])]
	public function getHolidayDay(string $language, int $month, int $day): Response {
		$holidayDay = $this->holidayService->getHolidayDay($language, $day, $month);
		$response = new JsonResponse($holidayDay);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{language<^\S{2}$>}/{id<\d+>}', name: 'get_one', methods: ['GET'])]
	public function getOne(string $language, int $id): Response {
		$holiday = $this->holidayService->getHoliday($language, $id);
		if ($holiday == null) {
			throw new NotFoundHttpException("Holiday with this id ($id) in language \"$language\" does not exist.");
		}
		$response = new JsonResponse($holiday);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}
}
