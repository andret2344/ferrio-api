<?php

namespace App\Controller;

use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\Language;
use App\Repository\FixedHolidayRepository;
use App\Repository\FloatingHolidayRepository;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/manage', name: 'manage_')]
class ManageController extends AbstractController {
	public function __construct(private readonly LanguageRepository        $languageRepository,
								private readonly FixedHolidayRepository    $fixedHolidayRepository,
								private readonly FloatingHolidayRepository $floatingHolidayRepository) {
	}

	#[Route('/', name: 'index')]
	public function index(): Response {
		$languages = $this->languageRepository->findBy([], ['code' => 'ASC']);
		return $this->render('manage/index.html.twig', [
			'languages' => $languages
		]);
	}

	#[Route('/{code<^\S{2}$>}', name: 'language')]
	public function language(string $code): Response {
		$language = $this->languageRepository->findOneBy(['code' => $code]);
		return $this->render('manage/translate.html.twig', [
			'language' => $language,
		]);
	}

	#[Route('/create', name: 'create')]
	public function create(Request $request, EntityManagerInterface $entityManager): Response {
		$action = $request->request->get('action');
		if ($action === 'update') {
			$id = $request->request->get('metadata_id');
			$name = $request->request->get('name');
			$desc = $request->request->get('description');
			/** @var FixedHoliday $found */
			$found = $this->fixedHolidayRepository->findOneBy(['metadata' => $id, 'language' => 'pl']);
			$found->setName($name);
			$found->setDescription($desc);
			$entityManager->persist($found);
			$entityManager->flush();
		}
		if ($action === 'create') {
			$month = $request->request->get('month');
			$day = $request->request->get('day');
			$name = $request->request->get('name');
			$desc = $request->request->get('description');
			$metadata = new FixedHolidayMetadata(null, $month, $day, 0, null, null);
			$entityManager->persist($metadata);
			/** @var Language $language */
			$language = $this->languageRepository->findOneBy(['code' => 'pl']);
			$holiday = new FixedHoliday($language, $metadata, $name, $desc ?? '', '');
			$entityManager->persist($holiday);
			$entityManager->flush();
		}
		$fixedHolidays = $this->fixedHolidayRepository->findAllByLanguage('pl');
		$floatingHolidays = $this->floatingHolidayRepository->findBy(['language' => 'pl']);
		return $this->render('manage/create.html.twig', [
			'fixed_holidays' => $fixedHolidays,
			'floating_holidays' => $floatingHolidays
		]);
	}
}
