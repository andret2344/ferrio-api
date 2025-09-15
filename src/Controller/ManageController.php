<?php

namespace App\Controller;

use App\Entity\Country;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FloatingHoliday;
use App\Entity\Language;
use App\Repository\FixedHolidayRepository;
use App\Repository\FixedMetadataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/manage', name: 'manage_')]
class ManageController extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface  $entityManager,
		private readonly FixedHolidayRepository  $fixedHolidayRepository,
		private readonly FixedMetadataRepository $fixedMetadataRepository)
	{
	}

	#[Route('/', name: 'index')]
	public function index(): Response
	{
		$languages = $this->entityManager->getRepository(Language::class)
			->findBy([], ['code' => 'ASC']);
		return $this->render('manage/index.html.twig', [
			'languages' => $languages
		]);
	}

	#[Route('/translate/{page}/{from<^\S{2}$>}/{to<^\S{2}$>}', name: 'translate')]
	public function translate(Request $request, int $page, string $from, string $to): Response
	{
		$action = $request->request->get('action');
		$repository = $this->entityManager->getRepository(Language::class);
		if ($action === 'update') {
			$id = $request->request->get('metadata_id');
			$name = $request->request->get('name');
			$desc = $request->request->get('description');
			/** @var FixedHolidayMetadata $metadata */
			$metadata = $this->fixedMetadataRepository->findOneBy(['id' => $id]);
			/** @var FixedHoliday|null $holiday */
			$holiday = $this->fixedHolidayRepository->findOneBy(['metadata' => $id, 'language' => $to]);
			/** @var Language $language */
			$language = $repository->findOneBy(['code' => $to]);
			if ($holiday == null) {
				$holiday = new FixedHoliday($language, $metadata, $name, $desc, '');
			} else {
				$holiday
					->setName($name)
					->setDescription($desc);
			}
			$this->entityManager->persist($holiday);
			$this->entityManager->flush();
		}
		$languageFrom = $repository->findOneBy(['code' => $from]);
		$languageTo = $repository->findOneBy(['code' => $to]);
		$languages = $repository->findAll();
		$holidays = $this->fixedHolidayRepository->findAllAggregatedById($from, $to, ($page - 1) * 100, 100);
		$pages = ceil($this->fixedMetadataRepository->count() / 100);
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
	public function translateDefault(Request $request, string $from, string $to): Response
	{
		return $this->translate($request, 1, $from, $to);
	}

	#[Route('/create', name: 'create')]
	public function create(Request $request): Response
	{
		return $this->createPage($request, 1);
	}

	#[Route('/create/{page}', name: 'create_page')]
	public function createPage(Request $request, int $page): Response
	{
		$action = $request->request->get('action');
		if ($action === 'update') {
			$id = $request->request->get('metadata_id');
			$name = $request->request->get('name');
			$desc = $request->request->get('description');
			$mature = (bool)$request->request->get('mature');
			/** @var FixedHoliday $found */
			$found = $this->fixedHolidayRepository->findOneBy(['metadata' => $id, 'language' => 'pl']);
			$found->setName($name);
			$found->setDescription($desc);
			$found->getMetadata()->matureContent = $mature;
			$this->entityManager->persist($found);
			$this->entityManager->flush();
		}
		if ($action === 'create') {
			$month = $request->request->get('month');
			$day = $request->request->get('day');
			$name = $request->request->get('name');
			$desc = $request->request->get('description');
			$country = $this->getCountry($request->request->get('country'));
			$mature = (bool)$request->request->get('mature');
			$metadata = new FixedHolidayMetadata($month, $day, 0, $country, null, $mature);
			$this->entityManager->persist($metadata);
			/** @var Language $language */
			$language = $this->entityManager->getRepository(Language::class)
				->findOneBy(['code' => 'pl']);
			$holiday = new FixedHoliday($language, $metadata, $name, $desc ?? '', '');
			$this->entityManager->persist($holiday);
			$this->entityManager->flush();
		}
		$fixedHolidays = $this->fixedHolidayRepository->findAllByLanguage('pl', ($page - 1) * 100, 100, true);
		$floatingHolidays = $this->entityManager->getRepository(FloatingHoliday::class)
			->findBy(['language' => 'pl']);
		$countries = $this->entityManager->getRepository(Country::class)
			->findAll();
		$pages = ceil($this->fixedMetadataRepository->count() / 100);
		return $this->render('manage/create.html.twig', [
			'fixed_holidays' => $fixedHolidays,
			'floating_holidays' => $floatingHolidays,
			'countries' => $countries,
			'page' => $page,
			'pages' => $pages
		]);
	}

	#[Route('/check', name: 'check')]
	public function check(Request $request): Response
	{
		return $this->checkLanguage($request, 'pl');
	}

	#[Route('/check/{lang<^\S{2}$>}', name: 'check_language')]
	public function checkLanguage(Request $request, string $lang): Response
	{
		$action = $request->request->get('action');
		$result = [];
		if ($action === 'check') {
			$holidays = preg_split("/[\r\n]+/", $request->request->get('holidays'));
			if ($request->request->get('holidays')) {
				$result = $this->fixedHolidayRepository->check($lang, $holidays);
			}
		}
		$language = $this->entityManager->getRepository(Language::class)
			->findOneBy(['code' => $lang]);
		$languages = $this->entityManager->getRepository(Language::class)
			->findAll();
		return $this->render('manage/check.html.twig', [
			'language' => $language,
			'languages' => $languages,
			'result' => $result
		]);
	}

	private function getCountry(?string $country): Country|null
	{
		if ($country === null || $country === 'null') {
			return null;
		}
		return $this->entityManager->getRepository(Country::class)
			->findOneBy(['isoCode' => $country]);
	}
}
