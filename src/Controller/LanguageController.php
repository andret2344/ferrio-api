<?php

namespace App\Controller;

use App\Service\HolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
		return new Response(json_encode($this->holidayService->getLanguages()));
	}

	#[Route('/{lang}', name: 'get_one', methods: ['GET'])]
	public function getOne(string $lang): Response {
		$language = $this->holidayService->getLanguage($lang);
		if ($language === null) {
			throw new NotFoundHttpException();
		}
		return new Response(json_encode($language));
	}
}
