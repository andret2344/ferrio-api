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
	private HolidayService $holidayService;

	public function __construct(HolidayService $holidayService) {
		$this->holidayService = $holidayService;
	}

	#[Route('/{language}', name: 'get_all', methods: ['GET'])]
	public function getAll(string $language): Response {
		/**
		 * @var HolidayDay[] $holidayDays
		 */
		$holidayDays = $this->holidayService->getHolidays($language);
		$response = new JsonResponse($holidayDays);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{language}/{id}', name: 'get_one', methods: ['GET'])]
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
