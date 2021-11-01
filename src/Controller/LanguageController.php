<?php

namespace App\Controller;

use App\Entity\Language;
use App\Service\HolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/language', name: 'language_')]
class LanguageController extends AbstractController {
	private HolidayService $holidayService;

	public function __construct(HolidayService $holidayService) {
		$this->holidayService = $holidayService;
	}

	#[Route('/', name: 'get_all', methods: ['GET'])]
	public function getAll(): Response {
		/**
		 * @var Language[] $languages
		 */
		$languages = $this->holidayService->getLanguages();
		$response = new JsonResponse($languages);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{lang}', name: 'get_one', methods: ['GET'])]
	public function getOne(string $lang): Response {
		/**
		 * @var Language $language
		 */
		$language = $this->holidayService->getLanguage($lang);
		if ($language === null) {
			throw new NotFoundHttpException();
		}
		$response = new JsonResponse($language);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}
}
