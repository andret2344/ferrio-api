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

	/**
	 * Endpoints given their own slice in a per-version doughnut; the rest are summed into "Other".
	 * The cap exists because a doughnut encodes its categories in colour alone, and `color()` in
	 * assets/charts.ts wraps around after the palette's 8 slots — a 9th endpoint would repeat a hue
	 * and two slices would be indistinguishable. Keep this at (palette size - 1) to leave room for
	 * the "Other" slice.
	 */
	private const int TOP_ENDPOINTS = 7;

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
			'version_chart' => $this->versionChart($stats),
			'version_charts' => $this->versionCharts($stats),
		]);
	}

	#[Route('/holidays', name: '_holidays', methods: ['GET'])]
	public function holidays(): Response
	{
		/** @var Language[] $languages */
		$languages = $this->entityManager->getRepository(Language::class)->findBy([], ['code' => 'ASC']);
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
	 * How the window's traffic splits between API versions — one slice per version. This also carries
	 * the per-version totals that used to sit in stat tiles above the charts, which is why the counts
	 * go into the slice labels; the grand total is the card's note in the template.
	 *
	 * @param array<string, mixed> $stats
	 *
	 * @return array<string, mixed>
	 */
	private function versionChart(array $stats): array
	{
		$totals = $stats['version_totals'];

		return self::doughnut($totals, $stats['total'], 'of all hits');
	}

	/**
	 * One doughnut per API version, showing how that version's traffic splits across its endpoints.
	 * Built by looping over the versions actually present in the window rather than a hardcoded pair,
	 * so a future v4 brings its own chart along without touching this page.
	 *
	 * @param array<string, mixed> $stats
	 *
	 * @return list<array{version: string, spec: array<string, mixed>}>
	 */
	private function versionCharts(array $stats): array
	{
		$charts = [];
		foreach ($stats['versions'] as $version) {
			$totals = $stats['paths_by_version'][$version] ?? [];
			if ($totals === []) {
				continue;
			}
			$charts[] = ['version' => $version, 'spec' => self::endpointDoughnut($totals)];
		}
		return $charts;
	}

	/**
	 * Everything past the busiest TOP_ENDPOINTS is folded into one "Other" slice, so the chart can
	 * never outgrow the palette (see TOP_ENDPOINTS).
	 *
	 * @param array<string, int> $totals endpoint => hits, busiest first
	 *
	 * @return array<string, mixed>
	 */
	private static function endpointDoughnut(array $totals): array
	{
		$slices = array_slice($totals, 0, self::TOP_ENDPOINTS, true);
		$rest = array_slice($totals, self::TOP_ENDPOINTS, null, true);
		if ($rest !== []) {
			$slices[sprintf('Other (%d endpoints)', count($rest))] = array_sum($rest);
		}

		return self::doughnut($slices, array_sum($slices), 'of this version');
	}

	/**
	 * The page carries no table, so each slice's hit count goes into its own label — the legend is
	 * where the numbers are read, and colour is not the only thing carrying them. The share is a
	 * hover-only note instead, to keep the legend from wrapping.
	 *
	 * @param array<string, int> $slices label => hits, in the order they should be drawn
	 * @param int $total denominator for the share note; slices always sum to it
	 * @param string $of tail of the share note, e.g. "of this version"
	 *
	 * @return array<string, mixed>
	 */
	private static function doughnut(array $slices, int $total, string $of): array
	{
		$labels = [];
		$notes = [];
		foreach ($slices as $label => $hits) {
			$labels[] = sprintf('%s (%s)', $label, number_format($hits, 0, '.', ' '));
			$notes[] = sprintf('%s%% %s', number_format(100 * $hits / $total, 1), $of);
		}

		return [
			'kind' => 'doughnut',
			'labels' => $labels,
			'series' => [['label' => 'Hits', 'data' => array_values($slices)]],
			'notes' => $notes,
		];
	}
}
