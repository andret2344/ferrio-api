<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Country;
use App\Entity\FixedHoliday;
use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidayMetadata;
use App\Entity\FixedHolidaySuggestion;
use App\Entity\FloatingHoliday;
use App\Entity\FloatingHolidayError;
use App\Entity\FloatingHolidayMetadata;
use App\Entity\FloatingHolidaySuggestion;
use App\Entity\Language;
use App\Entity\ReportState;
use App\Enum\ReportKind;
use App\Repository\FixedHolidayRepository;
use App\Service\AdminMetricsService;
use App\Service\FirebaseUserLookup;
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
		return $this->render('admin/index.html.twig', [
			'languages' => $languages,
			'fixedHolidayCount' => $this->metrics->fixedHolidayCount(),
			'floatingHolidayCount' => $this->metrics->floatingHolidayCount(),
			'tagCount' => $this->metrics->tagCount(),
			'translationCounts' => $this->metrics->translationCountsByLanguage(),
			'translationTotal' => $this->metrics->translationTotal(),
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

		$countries = $this->entityManager->getRepository(Country::class)->findAll();
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
			'source' => ['name' => '', 'description' => ''],
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
			$context['algorithm'] = \App\Enum\Algorithm::HARDCODED_DATES->value;
			$context['algorithmArgs'] = '';
			$context['algorithms'] = array_map(fn(\App\Enum\Algorithm $a) => $a->value, \App\Enum\Algorithm::cases());
			$context['algorithmExamples'] = $this->algorithmExamples();
			$context['backUrl'] = $this->generateUrl('admin_floating');
			$context['backLabel'] = 'Floating';
		}

		return $this->render('admin/holiday_detail.html.twig', $context);
	}

	#[Route('/holiday/{kind<fixed|floating>}/{id<\d+>}', name: 'holiday_detail')]
	public function holidayDetail(string $kind, int $id): Response
	{
		$metadataClass = $kind === 'fixed' ? FixedHolidayMetadata::class : FloatingHolidayMetadata::class;
		$metadata = $this->entityManager->getRepository($metadataClass)->find($id);
		if ($metadata === null) {
			throw $this->createNotFoundException();
		}

		$languageRepository = $this->entityManager->getRepository(Language::class);
		/** @var Language[] $languages */
		$languages = $languageRepository->findBy([], ['code' => 'ASC']);
		$targets = array_values(array_filter($languages, fn(Language $l) => $l->code !== Language::DEFAULT_CODE));

		$source = ['name' => '', 'description' => ''];
		$translations = [];
		foreach ($metadata->holidays as $holiday) {
			$entry = ['name' => $holiday->name, 'description' => $holiday->description];
			if ($holiday->language->code === Language::DEFAULT_CODE) {
				$source = $entry;
			} else {
				$translations[$holiday->language->code] = $entry;
			}
		}

		$countries = $this->entityManager->getRepository(Country::class)->findAll();
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
			$context['algorithms'] = array_map(fn(\App\Enum\Algorithm $a) => $a->value, \App\Enum\Algorithm::cases());
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
			'nth_day_then_next_day_of_week' => "{\n  \"nth\": 1,\n  \"dayOfWeek\": 1,\n  \"month\": 7,\n  \"afterDayOfWeek\": 2\n}",
			'leap_year_date' => "{\n  \"leapDay\": 29,\n  \"leapMonth\": 2,\n  \"nonLeapDay\": 1,\n  \"nonLeapMonth\": 3\n}",
			'hardcoded_dates' => $hardcoded,
			'earth_hour' => "{}",
			'fixed_date_with_changes' => "{\n  \"defaultDay\": 1,\n  \"defaultMonth\": 5,\n  \"changes\": []\n}",
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
			->leftJoin('m.categories', 'cat')
			->addSelect('cat')
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
		]);
	}

	#[Route('/reports', name: 'reports')]
	public function reports(): Response
	{
		$fixedSuggestions = $this->entityManager->getRepository(FixedHolidaySuggestion::class)
			->findBy([], ['datetime' => 'DESC']);
		$floatingSuggestions = $this->entityManager->getRepository(FloatingHolidaySuggestion::class)
			->findBy([], ['datetime' => 'DESC']);
		$fixedErrors = $this->entityManager->getRepository(FixedHolidayError::class)
			->findBy([], ['datetime' => 'DESC']);
		$floatingErrors = $this->entityManager->getRepository(FloatingHolidayError::class)
			->findBy([], ['datetime' => 'DESC']);

		$uids = array_merge(
			array_map(fn($s) => $s->userId, $fixedSuggestions),
			array_map(fn($s) => $s->userId, $floatingSuggestions),
			array_map(fn($e) => $e->userId, $fixedErrors),
			array_map(fn($e) => $e->userId, $floatingErrors),
		);
		$users = $this->firebaseUserLookup->lookup($uids);

		$fixedMetadataIds = array_values(array_unique(array_filter(array_merge(
			array_map(fn($s) => $s->holiday?->id, $fixedSuggestions),
			array_map(fn($e) => $e->metadata?->id, $fixedErrors),
		))));
		$floatingMetadataIds = array_values(array_unique(array_filter(array_merge(
			array_map(fn($s) => $s->holiday?->id, $floatingSuggestions),
			array_map(fn($e) => $e->metadata?->id, $floatingErrors),
		))));

		$fixedHolidaysPl = [];
		if (!empty($fixedMetadataIds)) {
			/** @var FixedHoliday[] $rows */
			$rows = $this->entityManager->getRepository(FixedHoliday::class)
				->createQueryBuilder('h')
				->where('h.language = :lang')
				->andWhere('h.metadata IN (:ids)')
				->setParameter('lang', Language::DEFAULT_CODE)
				->setParameter('ids', $fixedMetadataIds)
				->getQuery()
				->getResult();
			foreach ($rows as $h) {
				$fixedHolidaysPl[$h->metadata->id] = [
					'name' => $h->name,
					'description' => $h->description,
				];
			}
		}

		$floatingHolidaysPl = [];
		if (!empty($floatingMetadataIds)) {
			/** @var FloatingHoliday[] $rows */
			$rows = $this->entityManager->getRepository(FloatingHoliday::class)
				->createQueryBuilder('h')
				->where('h.language = :lang')
				->andWhere('h.metadata IN (:ids)')
				->setParameter('lang', Language::DEFAULT_CODE)
				->setParameter('ids', $floatingMetadataIds)
				->getQuery()
				->getResult();
			foreach ($rows as $h) {
				$floatingHolidaysPl[$h->metadata->id] = [
					'name' => $h->name,
					'description' => $h->description,
				];
			}
		}

		return $this->render('admin/reports.html.twig', [
			'fixedSuggestions' => $fixedSuggestions,
			'floatingSuggestions' => $floatingSuggestions,
			'fixedErrors' => $fixedErrors,
			'floatingErrors' => $floatingErrors,
			'users' => $users,
			'report_states' => array_column(ReportState::cases(), 'value'),
			'fixedHolidaysPl' => $fixedHolidaysPl,
			'floatingHolidaysPl' => $floatingHolidaysPl,
		]);
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
				if ($this->entityManager->getRepository($kind->metadataClass())->find($holidayId) === null) {
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
		$affected = $update->getQuery()->execute();

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
