<?php

namespace App\Controller;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use App\Service\HolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/language', name: 'language_')]
class LanguageController extends AbstractController {
	private LanguageRepository $languageRepository;
	private HolidayService $holidayService;

	public function __construct(LanguageRepository $languageRepository, HolidayService $holidayService) {
		$this->languageRepository = $languageRepository;
		$this->holidayService = $holidayService;
	}

	#[Route('/', name: 'get_all', methods: ['GET'])]
	public function getAll(): Response {
		/**
		 * @var Language[] $languages
		 */
		$languages = $this->languageRepository->findAll();
		$response = new JsonResponse($languages);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{code}', name: 'get_one', methods: ['GET'])]
	public function getOne(string $code): Response {
		/**
		 * @var Language $language
		 */
		$language = $this->languageRepository->findOneBy(['code' => $code]);
		if ($language === null) {
			throw new NotFoundHttpException();
		}
		$response = new JsonResponse($language);
		$response->headers->set("Content-Length", strlen($response->getContent()));
		return $response;
	}

	#[Route('/{code}/migrate', name: 'migrate', methods: ['GET'])]
	public function migrate(string $code): Response {
		/**
		 * @var Language[] $language
		 */
		$language = $this->languageRepository->findBy(['code' => $code]);
		$this->holidayService->migrate($language[0]);
		return new Response("");
	}
}
