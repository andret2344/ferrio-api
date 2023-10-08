<?php

namespace App\Controller\v1;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(['/language', '/v1/language'], name: 'v1_language_')]
class LanguageControllerV1 extends AbstractController {
	public function __construct(private readonly LanguageRepository $languageRepository,
								private readonly Logger             $log = new Logger('LanguageControllerV1')) {
		$log->pushHandler(new StreamHandler('log/latest.log'));
	}

	#[Route('/', name: 'get_all', methods: ['GET'])]
	public function getAll(): Response {
		$this->log->info("/");
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
		$this->log->info("/$code");
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
}
