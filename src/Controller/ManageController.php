<?php

namespace App\Controller;

use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\Language;
use App\Repository\CountryRepository;
use App\Repository\FixedHolidayRepository;
use App\Repository\FixedMetadataRepository;
use App\Repository\FloatingHolidayRepository;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/manage', name: 'manage_')]
class ManageController extends AbstractController {
	public function __construct(
		private readonly LanguageRepository        $languageRepository,
		private readonly FixedHolidayRepository    $fixedHolidayRepository,
		private readonly FloatingHolidayRepository $floatingHolidayRepository,
		private readonly FixedMetadataRepository   $fixedMetadataRepository,
		private readonly CountryRepository         $countryRepository) {
	}

	#[Route('/', name: 'index')]
	public function index(): Response {
		$languages = $this->languageRepository->findBy([], ['code' => 'ASC']);
		return $this->render('manage/index.html.twig', [
			'languages' => $languages
		]);
	}

	#[Route('/translate/{page}/{from<^\S{2}$>}/{to<^\S{2}$>}', name: 'translate')]
	public function translate(Request $request, EntityManagerInterface $entityManager,
							  int     $page, string $from, string $to): Response {
		$action = $request->request->get('action');
		if ($action === 'update') {
			$id = $request->request->get('metadata_id');
			$name = $request->request->get('name');
			$desc = $request->request->get('description');
			/** @var FixedHolidayMetadata $metadata */
			$metadata = $this->fixedMetadataRepository->findOneBy(['id' => $id]);
			/** @var FixedHoliday|null $holiday */
			$holiday = $this->fixedHolidayRepository->findOneBy(['metadata' => $id, 'language' => $to]);
			/** @var Language $language */
			$language = $this->languageRepository->findOneBy(['code' => $to]);
			if ($holiday == null) {
				$holiday = new FixedHoliday($language, $metadata, $name, $desc, null);
			} else {
				$holiday
					->setName($name)
					->setDescription($desc);
			}
			$entityManager->persist($holiday);
			$entityManager->flush();
		}
		$languageFrom = $this->languageRepository->findOneBy(['code' => $from]);
		$languageTo = $this->languageRepository->findOneBy(['code' => $to]);
		$languages = $this->languageRepository->findAll();
		$holidays = $this->fixedHolidayRepository->findAllAggregatedById($from, $to, ($page - 1) * 100, 100);
		$pages = ceil($this->fixedMetadataRepository->count([]) / 100);
		return $this->render('manage/translate.html.twig', [
			'languageFrom' => $languageFrom,
			'languageTo' => $languageTo,
			'languages' => $languages,
			'holidays' => $holidays,
			'page' => $page,
			'pages' => $pages
		]);
	}

	#[Route('/translate/{from<^\S{2}$>}/{to<^\S{2}$>}', name: 'translate_default')]
	public function translateDefault(Request $request, EntityManagerInterface $entityManager, string $from, string $to): Response {
		return $this->translate($request, $entityManager, 1, $from, $to);
	}

	#[Route('/create', name: 'create')]
	public function create(Request $request, EntityManagerInterface $entityManager): Response {
		return $this->createPage($request, $entityManager, 1);
	}

	#[Route('/create/{page}', name: 'create_page')]
	public function createPage(Request $request, EntityManagerInterface $entityManager, int $page): Response {
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
			$country = $this->getCountry($request->request->get('country'));
			$metadata = new FixedHolidayMetadata(null, $month, $day, 0, $country, null, false);
			$entityManager->persist($metadata);
			/** @var Language $language */
			$language = $this->languageRepository->findOneBy(['code' => 'pl']);
			$holiday = new FixedHoliday($language, $metadata, $name, $desc ?? '', '');
			$entityManager->persist($holiday);
			$entityManager->flush();
		}
		$fixedHolidays = $this->fixedHolidayRepository->findAllByLanguage('pl', ($page - 1) * 100, 100);
		$floatingHolidays = $this->floatingHolidayRepository->findBy(['language' => 'pl']);
		$countries = $this->countryRepository->findAll();
		$pages = ceil($this->fixedMetadataRepository->count([]) / 100);
		return $this->render('manage/create.html.twig', [
			'fixed_holidays' => $fixedHolidays,
			'floating_holidays' => $floatingHolidays,
			'countries' => $countries,
			'page' => $page,
			'pages' => $pages
		]);
	}

	#[Route('/check', name: 'check')]
	public function check(Request $request): Response {
		return $this->checkLanguage($request, 'pl');
	}

	#[Route('/check/{lang<^\S{2}$>}', name: 'check_language')]
	public function checkLanguage(Request $request, string $lang): Response {
		$action = $request->request->get('action');
		$result = [];
		if ($action === 'check') {
			$holidays = preg_split("/[\r\n]+/", $request->request->get('holidays'));
			if ($request->request->get('holidays')) {
				$result = $this->fixedHolidayRepository->check($lang, $holidays);
			}
		}
		$language = $this->languageRepository->findOneBy(['code' => $lang]);
		$languages = $this->languageRepository->findAll();
		return $this->render('manage/check.html.twig', [
			'language' => $language,
			'languages' => $languages,
			'result' => $result
		]);
	}

	private function getCountry(?string $country) {
		if ($country === null || $country === 'null') {
			return null;
		}
		return $this->countryRepository->findOneBy(['isoCode' => $country]);
	}
}
