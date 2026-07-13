<?php

namespace App\Controller;

use App\Entity\Language;
use App\Enum\Algorithm;
use App\Enum\ApiHitGrouping;
use App\Service\AdminMetricsService;
use App\Service\AdminUserService;
use App\Service\ApiHitStats;
use App\Service\BanService;
use App\Service\FirebaseUserLookup;
use Doctrine\ORM\EntityManagerInterface;
use Locale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The three read-only analytics pages grouped under "Stats" in the admin drawer: API traffic,
 * holiday/translation coverage and end-user reporting activity.
 */
#[Route('/admin/stats', name: 'admin_stats')]
class AdminStatsController extends AbstractController
{
	/**
	 * Selectable "last N days" windows for the API traffic page; 0 means no lower bound.
	 */
	private const array RANGES = [1, 7, 30, 90, 365, 0];

	/** Reporters listed in the top-reporters chart; the table below it still shows everyone. */
	private const int TOP_REPORTERS = 10;

	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly AdminMetricsService    $metrics,
		private readonly AdminUserService       $users,
		private readonly BanService             $banService,
		private readonly FirebaseUserLookup     $firebaseUserLookup,
	)
	{
	}

	#[Route('', name: '', methods: ['GET'])]
	public function api(Request $request, ApiHitStats $apiHitStats): Response
	{
		$grouping = ApiHitGrouping::tryFrom((string)$request->query->get('group')) ?? ApiHitGrouping::DAY;
		$days = filter_var($request->query->get('days'), FILTER_VALIDATE_INT);
		if (!in_array($days, self::RANGES, true)) {
			$days = 30;
		}
		$stats = $apiHitStats->collect($grouping, $days);

		return $this->render('admin/stats/api.html.twig', [
			'grouping' => $grouping->value,
			'groupings' => array_column(ApiHitGrouping::cases(), 'value'),
			'days' => $days,
			'ranges' => self::RANGES,
			'stats' => $stats,
			'traffic_chart' => $this->trafficChart($stats),
			'endpoint_chart' => $this->endpointChart($stats),
		]);
	}

	#[Route('/holidays', name: '_holidays', methods: ['GET'])]
	public function holidays(): Response
	{
		/** @var Language[] $languages */
		$languages = $this->entityManager->getRepository(Language::class)
			->findBy([], ['code' => 'ASC']);
		$targets = array_values(array_filter($languages, fn(Language $l) => $l->code !== Language::DEFAULT_CODE));

		$fixedTotal = $this->metrics->fixedHolidayCount();
		$floatingTotal = $this->metrics->floatingHolidayCount();
		$countsByKind = $this->metrics->translationCountsByLanguageAndKind();

		$rows = [];
		$missingTotal = 0;
		foreach ($targets as $language) {
			$fixed = $countsByKind['fixed'][$language->code] ?? 0;
			$floating = $countsByKind['floating'][$language->code] ?? 0;
			$missing = max(0, $fixedTotal - $fixed) + max(0, $floatingTotal - $floating);
			$missingTotal += $missing;
			$rows[] = [
				'language' => $language,
				'fixed' => $fixed,
				'fixed_missing' => max(0, $fixedTotal - $fixed),
				'floating' => $floating,
				'floating_missing' => max(0, $floatingTotal - $floating),
				'missing' => $missing,
			];
		}

		$byMonth = $this->metrics->fixedCountsByMonth();
		$byAlgorithm = $this->metrics->floatingCountsByAlgorithm();

		return $this->render('admin/stats/holidays.html.twig', [
			'rows' => $rows,
			'fixedTotal' => $fixedTotal,
			'floatingTotal' => $floatingTotal,
			'missingTotal' => $missingTotal,
			'languageCount' => $this->metrics->languageCount(),
			'tagCount' => $this->metrics->tagCount(),
			'coverage_chart' => [
				'kind' => 'stacked_bar',
				'labels' => array_map(fn(Language $l) => $this->languageName($l), $targets),
				'series' => [
					['label' => 'Fixed translated', 'data' => array_column($rows, 'fixed')],
					['label' => 'Floating translated', 'data' => array_column($rows, 'floating')],
					['label' => 'Missing', 'data' => array_column($rows, 'missing')],
				],
			],
			'month_chart' => [
				'kind' => 'bar',
				'labels' => array_map(fn(int $m) => date('F', mktime(0, 0, 0, $m, 1)), array_keys($byMonth)),
				'series' => [['label' => 'Fixed holidays', 'data' => array_values($byMonth)]],
			],
			'algorithm_chart' => [
				'kind' => 'horizontal_bar',
				'labels' => array_map(fn(string $a) => Algorithm::from($a)->label(), array_keys($byAlgorithm)),
				'series' => [['label' => 'Floating holidays', 'data' => array_values($byAlgorithm)]],
			],
		]);
	}

	/**
	 * English display name of a language code, from ICU rather than the DB — the `name` column holds
	 * whatever the admin typed when creating the language (often the endonym), which makes for
	 * inconsistent chart labels. Falls back to the stored name when ICU does not know the code.
	 */
	private function languageName(Language $language): string
	{
		$name = Locale::getDisplayName($language->code, 'en');
		if ($name === '' || strcasecmp($name, $language->code) === 0) {
			return $language->name;
		}
		return $name;
	}

	#[Route('/users', name: '_users', methods: ['GET'])]
	public function users(): Response
	{
		$reporters = $this->users->reporters();
		$userIds = array_column($reporters, 'user_id');
		$profiles = $this->firebaseUserLookup->lookup($userIds);
		$reportsByMonth = $this->users->reportsByMonth();

		$kindTotals = ['fixed_suggestion' => 0, 'floating_suggestion' => 0, 'fixed_error' => 0, 'floating_error' => 0];
		foreach ($reporters as $row) {
			foreach ($row['counts'] as $kind => $count) {
				$kindTotals[$kind] += $count;
			}
		}

		$top = array_slice($reporters, 0, self::TOP_REPORTERS);

		return $this->render('admin/stats/users.html.twig', [
			'reporters' => $reporters,
			'bans' => $this->banService->getBans($userIds),
			'profiles' => $profiles,
			'totals' => $this->users->totals($reporters, $this->banService->count()),
			'kind_chart' => [
				'kind' => 'doughnut',
				'labels' => ['Fixed suggestions', 'Floating suggestions', 'Fixed errors', 'Floating errors'],
				'series' => [['label' => 'Reports', 'data' => array_values($kindTotals)]],
			],
			'top_chart' => [
				'kind' => 'horizontal_bar',
				'labels' => array_map(
					fn(array $row) => $this->label($row['user_id'], $profiles[$row['user_id']] ?? null),
					$top,
				),
				// The e-mail only shows on hover — the axis stays down to one short name per bar.
				'notes' => array_map(
					fn(array $row) => $this->truncate($profiles[$row['user_id']]['email'] ?? ''),
					$top,
				),
				'series' => [['label' => 'Reports', 'data' => array_column($top, 'total')]],
			],
			'timeline_chart' => [
				'kind' => 'line',
				'labels' => array_keys($reportsByMonth),
				'series' => [['label' => 'Reports', 'data' => array_values($reportsByMonth)]],
			],
		]);
	}

	/**
	 * Axis label of a reporter: their name. Reports are keyed by UID, but a UID on an axis says
	 * nothing to a human, so it only appears when Firebase knows neither a name nor an e-mail.
	 *
	 * @param array{email: ?string, name: ?string, avatar: string}|null $profile
	 */
	private function label(string $userId, ?array $profile): string
	{
		$name = trim((string)($profile['name'] ?? ''));
		if ($name !== '') {
			return $this->truncate($name);
		}
		$email = trim((string)($profile['email'] ?? ''));
		if ($email !== '') {
			return $this->truncate($email);
		}
		return $this->truncate($userId);
	}

	private function truncate(?string $value): string
	{
		$value = trim((string)$value);
		if (mb_strlen($value) <= 28) {
			return $value;
		}
		return mb_substr($value, 0, 27) . '…';
	}

	/**
	 * Hits over time, one line per API version. Periods come back newest-first from ApiHitStats
	 * (the table wants that order); a time axis wants the opposite.
	 *
	 * @param array<string, mixed> $stats
	 *
	 * @return array<string, mixed>
	 */
	private function trafficChart(array $stats): array
	{
		$periods = array_reverse($stats['periods']);

		return [
			'kind' => 'line',
			'labels' => $periods,
			'series' => array_map(
				fn(string $version) => [
					'label' => $version,
					'data' => array_map(fn(string $period) => $stats['version_cells'][$period][$version] ?? 0, $periods),
				],
				$stats['versions'],
			),
		];
	}

	/**
	 * @param array<string, mixed> $stats
	 *
	 * @return array<string, mixed>
	 */
	private function endpointChart(array $stats): array
	{
		$paths = array_slice($stats['paths'], 0, 10);

		return [
			'kind' => 'horizontal_bar',
			'labels' => $paths,
			'series' => [[
				'label' => 'Hits',
				'data' => array_map(fn(string $path) => $stats['path_totals'][$path], $paths),
			]],
		];
	}
}
