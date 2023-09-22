<?php

namespace App\Controller;

use App\Entity\Language;
use App\Repository\LanguageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/manage', name: 'manage_')]
class ManageController extends AbstractController {
	public function __construct(private readonly LanguageRepository $languageRepository) {
	}
	#[Route('/', name: 'index')]
	public function index(): Response {
		/** @var Language[] $languages */
		$languages = $this->languageRepository->findAll();
		return $this->render('manage/index.html.twig', [
			'languages' => $languages
		]);
	}
}
