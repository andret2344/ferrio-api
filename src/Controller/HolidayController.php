<?php

namespace App\Controller;

use App\Entity\Holiday;
use App\Repository\HolidayRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/holiday', name: 'holiday_')]
class HolidayController extends AbstractController {
	private HolidayRepository $holidayRepository;

	public function __construct(HolidayRepository $holidayRepository) {
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
		$holiday = $this->holidayRepository->findOneBy([
			'language' => $lang,
			'metadata' => $id
		]);
		if ($holiday == null) {
			throw new NotFoundHttpException("Holiday with this id ($id) in language \"$lang\" does not exist.");
		}
		$response = new JsonResponse($holiday);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}
}
