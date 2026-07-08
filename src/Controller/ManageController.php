<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Country;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FloatingHoliday;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\Language;
use App\Entity\Platform;
use App\Entity\ReportState;
use App\Enum\Algorithm;
use App\Enum\ReportKind;
use App\Repository\FixedHolidayRepository;
use App\Service\AdminMetricsService;
use App\Service\FirebaseUserLookup;
use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class ManageController extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly FixedHolidayRepository $fixedHolidayRepository,
		private readonly FirebaseUserLookup     $firebaseUserLookup,
		private readonly AdminMetricsService    $metrics,
	)
	{
	}

	#[Route('/', name: 'index')]
	public function index(): Response
	{
		$languages = $this->entityManager->getRepository(Language::class)
			->findBy([], ['code' => 'ASC']);
		$fixedTotal = $this->metrics->fixedHolidayCount();
		$floatingTotal = $this->metrics->floatingHolidayCount();
		return $this->render('admin/index.html.twig', [
			'languages' => $languages,
			'fixedHolidayCount' => $fixedTotal,
			'floatingHolidayCount' => $floatingTotal,
			'tagCount' => $this->metrics->tagCount(),
			'translationCountsByKind' => $this->metrics->translationCountsByLanguageAndKind(),
			'translationTotalsByKind' => ['fixed' => $fixedTotal, 'floating' => $floatingTotal],
			'reportCounts' => $this->metrics->reportCounts(),
		]);
	}

	#[Route('/holiday/{kind<fixed|floating>}/new', name: 'holiday_new')]
	public function holidayNew(string $kind, Request $request): Response
	{
		$languageRepository = $this->entityManager->getRepository(Language::class);
		/** @var Language[] $languages */
		$languages = $languageRepository->findBy([], ['code' => 'ASC']);
		$targets = array_values(array_filter($languages, fn(Language $l) => $l->code !== Language::DEFAULT_CODE));

		$countries = $this->entityManager->getRepository(Country::class)
			->findAll();
		$tags = $this->entityManager->getRepository(Category::class)
			->findBy([], ['slug' => 'ASC']);

		$context = [
			'kind' => $kind,
			'id' => null,
			'isNew' => true,
			'targets' => $targets,
			'countries' => $countries,
			'tags' => $tags,
			'selectedTagIds' => [],
			'source' => ['name' => '', 'description' => '', 'ai_generated' => false],
			'translations' => [],
			'countryCode' => null,
			'mature' => false,
		];

		if ($kind === 'fixed') {
			$month = filter_var($request->query->get('month'), FILTER_VALIDATE_INT);
			$context['day'] = '';
			$context['month'] = $month >= 1 && $month <= 12 ? (string)$month : '';
			$context['backUrl'] = $month >= 1 && $month <= 12
				? $this->generateUrl('admin_create_month', ['month' => $month])
				: $this->generateUrl('admin_create');
			$context['backLabel'] = 'Fixed';
		} else {
			$context['algorithm'] = Algorithm::HARDCODED_DATES->value;
			$context['algorithmArgs'] = '';
			$context['algorithms'] = array_map(fn(Algorithm $a) => $a->value, Algorithm::cases());
			$context['algorithmExamples'] = $this->algorithmExamples();
			$context['backUrl'] = $this->generateUrl('admin_floating');
			$context['backLabel'] = 'Floating';
		}

		$fromKind = $request->query->get('from');
		$fromId = filter_var($request->query->get('fromId'), FILTER_VALIDATE_INT);
		if (in_array($fromKind, ['fixed', 'floating'], true) && $fromKind !== $kind && $fromId !== false && $fromId > 0) {
			$sourceMetadataClass = $fromKind === 'fixed' ? FixedHolidayMetadata::class : FloatingHolidayMetadata::class;
			$sourceMetadata = $this->entityManager->getRepository($sourceMetadataClass)
				->find($fromId);
			if ($sourceMetadata !== null) {
				$source = ['name' => '', 'description' => '', 'ai_generated' => false];
				$translations = [];
				foreach ($sourceMetadata->holidays as $holiday) {
					$entry = ['name' => $holiday->name, 'description' => $holiday->description, 'ai_generated' => $holiday->aiGenerated];
					if ($holiday->language->code === Language::DEFAULT_CODE) {
						$source = $entry;
					} else {
						$translations[$holiday->language->code] = $entry;
					}
				}
				$context['source'] = $source;
				$context['translations'] = $translations;
				$context['countryCode'] = $sourceMetadata->country?->isoCode;
				$context['mature'] = $sourceMetadata->matureContent;
				$context['selectedTagIds'] = array_values(array_map(fn(Category $c) => $c->id, $sourceMetadata->categories->toArray()));
			}
		}

		return $this->render('admin/holiday_detail.html.twig', $context);
	}

	#[Route('/holiday/{kind<fixed|floating>}/{id<\d+>}', name: 'holiday_detail')]
	public function holidayDetail(string $kind, int $id): Response
	{
		$metadataClass = $kind === 'fixed' ? FixedHolidayMetadata::class : FloatingHolidayMetadata::class;
		$metadata = $this->entityManager->getRepository($metadataClass)
			->find($id);
		if ($metadata === null) {
			throw $this->createNotFoundException();
		}

		$languageRepository = $this->entityManager->getRepository(Language::class);
		/** @var Language[] $languages */
		$languages = $languageRepository->findBy([], ['code' => 'ASC']);
		$targets = array_values(array_filter($languages, fn(Language $l) => $l->code !== Language::DEFAULT_CODE));

		$source = ['name' => '', 'description' => '', 'ai_generated' => false];
		$translations = [];
		foreach ($metadata->holidays as $holiday) {
			$entry = ['name' => $holiday->name, 'description' => $holiday->description, 'ai_generated' => $holiday->aiGenerated];
			if ($holiday->language->code === Language::DEFAULT_CODE) {
				$source = $entry;
			} else {
				$translations[$holiday->language->code] = $entry;
			}
		}

		$countries = $this->entityManager->getRepository(Country::class)
			->findAll();
		$tags = $this->entityManager->getRepository(Category::class)
			->findBy([], ['slug' => 'ASC']);
		$selectedTagIds = array_values(array_map(fn(Category $c) => $c->id, $metadata->categories->toArray()));

		$context = [
			'kind' => $kind,
			'id' => $id,
			'isNew' => false,
			'targets' => $targets,
			'countries' => $countries,
			'tags' => $tags,
			'selectedTagIds' => $selectedTagIds,
			'source' => $source,
			'translations' => $translations,
			'countryCode' => $metadata->country?->isoCode,
			'mature' => $metadata->matureContent,
		];

		if ($kind === 'fixed') {
			/** @var FixedHolidayMetadata $metadata */
			$context['day'] = $metadata->day;
			$context['month'] = $metadata->month;
			$context['backUrl'] = $this->generateUrl('admin_create_month', ['month' => $metadata->month]);
			$context['backLabel'] = sprintf('Fixed · %s', date('F', mktime(0, 0, 0, $metadata->month, 1)));
		} else {
			/** @var FloatingHolidayMetadata $metadata */
			$context['algorithm'] = $metadata->algorithm->value;
			$context['algorithmArgs'] = $metadata->algorithmArgs;
			$context['algorithms'] = array_map(fn(Algorithm $a) => $a->value, Algorithm::cases());
			$context['algorithmExamples'] = $this->algorithmExamples();
			$context['backUrl'] = $this->generateUrl('admin_floating');
			$context['backLabel'] = 'Floating';
		}

		return $this->render('admin/holiday_detail.html.twig', $context);
	}

	/**
	 * @return array<string, string>
	 */
	private function algorithmExamples(): array
	{
		$currentYear = (int)date('Y');
		$hardcodedLines = [];
		for ($y = $currentYear - 2; $y <= $currentYear; $y++) {
			$hardcodedLines[] = sprintf('  "%d": "1.1"', $y);
		}
		$hardcoded = "{\n" . implode(",\n", $hardcodedLines) . "\n}";

		return [
			'nth_day_of_week_in_month' => "{\n  \"nth\": 4,\n  \"dayOfWeek\": 4,\n  \"month\": 11\n}",
			'last_nth_day_of_week_in_month' => "{\n  \"nth\": 1,\n  \"dayOfWeek\": 1,\n  \"month\": 5\n}",
			'first_day_of_week_after_date' => "{\n  \"dayOfWeek\": 6,\n  \"month\": 5,\n  \"day\": 19,\n  \"inclusive\": true\n}",
			'last_day_of_week_before_date' => "{\n  \"dayOfWeek\": 5,\n  \"month\": 3,\n  \"day\": 20,\n  \"inclusive\": true\n}",
			'nearest_day_of_week_to_date' => "{\n  \"dayOfWeek\": 6,\n  \"month\": 6,\n  \"day\": 17\n}",
			'nth_day_then_next_day_of_week' => "{\n  \"nth\": 1,\n  \"dayOfWeek\": 1,\n  \"month\": 7,\n  \"afterDayOfWeek\": 2\n}",
			'leap_year_date' => "{\n  \"leapDay\": 29,\n  \"leapMonth\": 2,\n  \"nonLeapDay\": 1,\n  \"nonLeapMonth\": 3\n}",
			'hardcoded_dates' => $hardcoded,
			'earth_hour' => "{}",
			'easter_offset' => "{\n  \"offset\": 60\n}",
			'fixed_date_with_changes' => "{\n  \"defaultDay\": 6,\n  \"defaultMonth\": 4,\n  \"changes\": [\n    {\n      \"fromYear\": 2023,\n      \"day\": 23,\n      \"month\": 4\n    }\n  ]\n}",
		];
	}

	#[Route('/create', name: 'create')]
	public function create(): Response
	{
		return $this->redirectToRoute('admin_create_month', ['month' => (int)date('m')]);
	}

	#[Route('/create/{month<^([1-9]|1[0-2])$>}', name: 'create_month')]
	public function createMonth(int $month): Response
	{
		$fixedHolidays = $this->fixedHolidayRepository->findAllByLanguage(Language::DEFAULT_CODE, matureContent: true, month: $month);
		return $this->render('admin/create.html.twig', [
			'fixed_holidays' => $fixedHolidays,
			'month' => $month,
			'translationCounts' => $this->metrics->fixedTranslationCountsByMetadata($month),
			'targetLanguageCount' => $this->metrics->targetLanguageCount(),
			'tagCounts' => $this->metrics->fixedTagCountsByMetadata($month),
		]);
	}

	#[Route('/floating', name: 'floating')]
	public function floating(): Response
	{
		$polishHolidays = $this->entityManager->getRepository(FloatingHoliday::class)
			->createQueryBuilder('h')
			->select('h', 'm')
			->join('h.metadata', 'm')
			->leftJoin('m.country', 'c')
			->addSelect('c')
			->where('h.language = :language')
			->setParameter('language', Language::DEFAULT_CODE)
			->orderBy('m.algorithm', 'ASC')
			->addOrderBy('h.name', 'ASC')
			->getQuery()
			->getResult();
		return $this->render('admin/floating.html.twig', [
			'floating_holidays' => $polishHolidays,
			'translationCounts' => $this->metrics->floatingTranslationCountsByMetadata(),
			'targetLanguageCount' => $this->metrics->targetLanguageCount(),
			'tagCounts' => $this->metrics->floatingTagCountsByMetadata(),
		]);
	}

	#[Route('/missing/{kind<fixed|floating>}', name: 'missing_overview')]
	public function missingOverview(string $kind): Response
	{
		$languages = $this->entityManager->getRepository(Language::class)
			->findBy([], ['code' => 'ASC']);
		$targets = array_values(array_filter(
			$languages,
			fn(Language $l) => $l->code !== Language::DEFAULT_CODE,
		));

		$countsByKind = $this->metrics->translationCountsByLanguageAndKind();
		$kindTotal = $kind === 'fixed'
			? $this->metrics->fixedHolidayCount()
			: $this->metrics->floatingHolidayCount();

		$rows = [];
		foreach ($targets as $language) {
			$translated = $countsByKind[$kind][$language->code] ?? 0;
			$missing = max(0, $kindTotal - $translated);
			$rows[] = [
				'language' => $language,
				'translated' => $translated,
				'total' => $kindTotal,
				'missing' => $missing,
			];
		}

		return $this->render('admin/missing_overview.html.twig', [
			'kind' => $kind,
			'rows' => $rows,
			'kindTotal' => $kindTotal,
		]);
	}

	#[Route('/missing/{kind<fixed|floating>}/{lang}', name: 'missing')]
	public function missing(string $kind, string $lang): Response
	{
		$language = $this->entityManager->getRepository(Language::class)
			->find($lang);
		if ($language === null || $language->code === Language::DEFAULT_CODE) {
			throw $this->createNotFoundException();
		}

		$entityClass = $kind === 'fixed' ? FixedHoliday::class : FloatingHoliday::class;
		$qb = $this->entityManager->getRepository($entityClass)
			->createQueryBuilder('h')
			->select('h', 'm')
			->join('h.metadata', 'm')
			->leftJoin('m.country', 'c')
			->addSelect('c')
			->leftJoin('m.categories', 'cat')
			->addSelect('cat')
			->where('h.language = :pl')
			->andWhere(sprintf(
				'm.id NOT IN (SELECT IDENTITY(t.metadata) FROM %s t WHERE t.language = :lang)',
				$entityClass,
			))
			->setParameter('pl', Language::DEFAULT_CODE)
			->setParameter('lang', $language->code);

		if ($kind === 'fixed') {
			$qb->orderBy('m.month', 'ASC')
				->addOrderBy('m.day', 'ASC')
				->addOrderBy('h.name', 'ASC');
		} else {
			$qb->orderBy('m.algorithm', 'ASC')
				->addOrderBy('h.name', 'ASC');
		}

		$holidays = $qb->getQuery()
			->getResult();

		return $this->render('admin/missing.html.twig', [
			'kind' => $kind,
			'language' => $language,
			'holidays' => $holidays,
		]);
	}

	/**
	 * @var array<string, array{kind: ReportKind, label: string, emptyLabel: string}>
	 */
	private const array REPORT_TYPES = [
		'fixed-suggestions' => [
			'kind' => ReportKind::FIXED_SUGGESTION,
			'label' => 'Fixed suggestions',
			'emptyLabel' => 'fixed holiday suggestions',
		],
		'floating-suggestions' => [
			'kind' => ReportKind::FLOATING_SUGGESTION,
			'label' => 'Floating suggestions',
			'emptyLabel' => 'floating holiday suggestions',
		],
		'fixed-errors' => [
			'kind' => ReportKind::FIXED_ERROR,
			'label' => 'Fixed errors',
			'emptyLabel' => 'fixed holiday error reports',
		],
		'floating-errors' => [
			'kind' => ReportKind::FLOATING_ERROR,
			'label' => 'Floating errors',
			'emptyLabel' => 'floating holiday error reports',
		],
	];

	#[Route('/reports', name: 'reports_index')]
	public function reportsIndex(): Response
	{
		return $this->redirectToRoute('admin_reports', ['type' => array_key_first(self::REPORT_TYPES)]);
	}

	#[Route('/reports/{type<fixed-suggestions|floating-suggestions|fixed-errors|floating-errors>}', name: 'reports')]
	public function reports(string $type, Request $request): Response
	{
		$config = self::REPORT_TYPES[$type];
		$kind = $config['kind'];
		$isError = !$kind->isSuggestion();
		$isFloating = $kind === ReportKind::FLOATING_SUGGESTION || $kind === ReportKind::FLOATING_ERROR;

		$filters = $this->parseReportFilters($request);
		$rows = $this->entityManager->getRepository($kind->entityClass())
			->findBy($this->buildReportCriteria($filters), ['datetime' => 'DESC']);

		$users = $this->firebaseUserLookup->lookup(array_map(fn($r) => $r->userId, $rows));

		// Error reports reference an existing holiday whose name/description we surface in the modal,
		// rendered in the language the user reported in (falling back to the PL source when that
		// translation is missing). Suggestions carry their own name/date/country, so no lookup is
		// needed. Keyed by "metadataId:languageCode".
		$holidaysReferred = [];
		if ($isError) {
			$metadataIds = array_values(array_unique(array_filter(
				array_map(fn($e) => $e->metadata?->id, $rows),
			)));
			if (!empty($metadataIds)) {
				$langCodes = array_values(array_unique(array_map(fn($e) => $e->language->code, $rows)));
				if (!in_array(Language::DEFAULT_CODE, $langCodes, true)) {
					$langCodes[] = Language::DEFAULT_CODE;
				}
				$holidayClass = $isFloating ? FloatingHoliday::class : FixedHoliday::class;
				/** @var FixedHoliday[]|FloatingHoliday[] $referredRows */
				$referredRows = $this->entityManager->getRepository($holidayClass)
					->createQueryBuilder('h')
					->where('h.language IN (:langs)')
					->andWhere('h.metadata IN (:ids)')
					->setParameter('langs', $langCodes)
					->setParameter('ids', $metadataIds)
					->getQuery()
					->getResult();
				foreach ($referredRows as $h) {
					$holidaysReferred[$h->metadata->id . ':' . $h->language->code] = [
						'name' => $h->name,
						'description' => $h->description,
					];
				}
			}
		}

		return $this->render('admin/reports.html.twig', [
			'type' => $type,
			'kind' => $kind->value,
			'isError' => $isError,
			'label' => $config['label'],
			'emptyLabel' => $config['emptyLabel'],
			'rows' => $rows,
			'users' => $users,
			'report_states' => array_column(ReportState::cases(), 'value'),
			'platforms' => Platform::values(),
			'filters' => $filters,
			'countries' => $this->entityManager->getRepository(Country::class)
				->findBy([], ['isoCode' => 'ASC']),
			'defaultLang' => Language::DEFAULT_CODE,
			'fixedHolidays' => $isFloating ? [] : $holidaysReferred,
			'floatingHolidays' => $isFloating ? $holidaysReferred : [],
		]);
	}

	private const string UNKNOWN_COUNTRY = 'unknown';

	/**
	 * @return array{platform: ?string, device_country: ?string, state: ?string}
	 */
	private function parseReportFilters(Request $request): array
	{
		$platform = $request->query->get('platform');
		$deviceCountry = $request->query->get('device_country');
		$state = $request->query->get('state');
		return [
			'platform' => in_array($platform, Platform::values(), true) ? $platform : null,
			'device_country' => $this->normalizeDeviceCountryFilter($deviceCountry),
			'state' => ReportState::tryFrom((string)$state)?->value,
		];
	}

	private function normalizeDeviceCountryFilter(mixed $raw): ?string
	{
		if (!is_string($raw) || $raw === '') {
			return null;
		}
		if (strtolower($raw) === self::UNKNOWN_COUNTRY) {
			return self::UNKNOWN_COUNTRY;
		}
		return strtoupper($raw);
	}

	/**
	 * @param array{platform: ?string, device_country: ?string, state: ?string} $filters
	 *
	 * @return array<string, mixed>
	 */
	private function buildReportCriteria(array $filters): array
	{
		$criteria = [];
		if ($filters['platform'] !== null) {
			$criteria['platform'] = Platform::from($filters['platform']);
		}
		if ($filters['device_country'] === self::UNKNOWN_COUNTRY) {
			$criteria['deviceCountry'] = null;
		} else {
			if ($filters['device_country'] !== null) {
				$country = $this->entityManager->getRepository(Country::class)
					->findOneBy(['isoCode' => $filters['device_country']]);
				if ($country !== null) {
					$criteria['deviceCountry'] = $country;
				}
			}
		}
		if ($filters['state'] !== null) {
			$criteria['reportState'] = ReportState::from($filters['state']);
		}
		return $criteria;
	}

	#[Route('/api/reports/stats', name: 'reports_stats', methods: ['GET'])]
	public function reportsStats(): JsonResponse
	{
		$stats = [];
		foreach (ReportKind::cases() as $kind) {
			$qb = $this->entityManager->getRepository($kind->entityClass())
				->createQueryBuilder('r');
			$byPlatform = $qb->select('COALESCE(r.platform, \'_unknown\') AS platform, COUNT(r.id) AS c')
				->groupBy('r.platform')
				->getQuery()
				->getArrayResult();

			$qb = $this->entityManager->getRepository($kind->entityClass())
				->createQueryBuilder('r');
			$byState = $qb->select('r.reportState AS state, COUNT(r.id) AS c')
				->groupBy('r.reportState')
				->getQuery()
				->getArrayResult();

			$qb = $this->entityManager->getRepository($kind->entityClass())
				->createQueryBuilder('r');
			$byCountry = $qb->select('COALESCE(IDENTITY(r.deviceCountry), \'_unknown\') AS country, COUNT(r.id) AS c')
				->groupBy('r.deviceCountry')
				->getQuery()
				->getArrayResult();

			$stats[$kind->value] = [
				'by_platform' => $this->collapseAgg($byPlatform, 'platform'),
				'by_state' => $this->collapseAgg($byState, 'state'),
				'by_device_country' => $this->collapseAgg($byCountry, 'country'),
			];
		}
		return $this->json($stats);
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 *
	 * @return array<string, int>
	 */
	private function collapseAgg(array $rows, string $key): array
	{
		$out = [];
		foreach ($rows as $row) {
			$k = $row[$key];
			if ($k instanceof BackedEnum) {
				$k = $k->value;
			}
			$out[(string)$k] = (int)$row['c'];
		}
		return $out;
	}

	#[Route('/reports/moderate', name: 'reports_moderate', methods: ['POST'])]
	public function moderateReport(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true);
		if (!is_array($data)) {
			throw new BadRequestHttpException('Invalid JSON body');
		}

		$kind = ReportKind::tryFrom((string)($data['kind'] ?? ''));
		if (!$kind) {
			throw new BadRequestHttpException('Invalid kind');
		}

		$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
		if ($id === false) {
			throw new BadRequestHttpException('Invalid id');
		}

		$state = ReportState::tryFrom((string)($data['report_state'] ?? ''));
		if (!$state) {
			throw new BadRequestHttpException('Invalid report_state');
		}

		$comment = $data['comment'] ?? null;
		if ($comment !== null) {
			$comment = trim((string)$comment);
			if ($comment === '') {
				$comment = null;
			}
		}

		$holidayId = null;
		if ($kind->isSuggestion()) {
			$holidayIdRaw = $data['holiday_id'] ?? null;
			if ($holidayIdRaw !== '' && $holidayIdRaw !== null) {
				$holidayId = filter_var($holidayIdRaw, FILTER_VALIDATE_INT);
				if ($holidayId === false || $holidayId <= 0) {
					return $this->json(['error' => 'Invalid holiday id', 'field' => 'holiday_id'], Response::HTTP_BAD_REQUEST);
				}
				if ($this->entityManager->getRepository($kind->metadataClass())
						->find($holidayId) === null) {
					return $this->json(['error' => 'Holiday does not exist', 'field' => 'holiday_id'], Response::HTTP_BAD_REQUEST);
				}
			}
		}

		$update = $this->entityManager->createQueryBuilder()
			->update($kind->entityClass(), 'r')
			->set('r.reportState', ':state')
			->set('r.comment', ':comment')
			->where('r.id = :id')
			->setParameter('state', $state)
			->setParameter('comment', $comment)
			->setParameter('id', $id);
		if ($kind->isSuggestion()) {
			$update->set('r.holiday', ':holiday')
				->setParameter('holiday', $holidayId);
		}
		$affected = $update->getQuery()
			->execute();

		if ($affected === 0) {
			return $this->json(['error' => 'Report not found'], Response::HTTP_NOT_FOUND);
		}

		$response = [
			'id' => $id,
			'report_state' => $state->value,
			'comment' => $comment,
		];
		if ($kind->isSuggestion()) {
			$response['holiday_id'] = $holidayId;
		}
		return $this->json($response);
	}

	#[Route('/reports/delete', name: 'reports_delete', methods: ['POST'])]
	public function deleteReport(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true);
		if (!is_array($data)) {
			throw new BadRequestHttpException('Invalid JSON body');
		}

		$kind = ReportKind::tryFrom((string)($data['kind'] ?? ''));
		if (!$kind) {
			throw new BadRequestHttpException('Invalid kind');
		}

		$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
		if ($id === false) {
			throw new BadRequestHttpException('Invalid id');
		}

		$affected = $this->entityManager->createQueryBuilder()
			->delete($kind->entityClass(), 'r')
			->where('r.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->execute();

		if ($affected === 0) {
			return $this->json(['error' => 'Report not found'], Response::HTTP_NOT_FOUND);
		}

		return $this->json(['id' => $id]);
	}

}
