<?php

namespace App\Controller;

use App\Entity\Country;
use App\Enum\Algorithm;
use App\Service\AlgorithmResolver;
use App\Service\WebSearchResults;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin/api')]
class GenerateDescriptionController extends AbstractController
{
	/**
	 * Server-side web search, run by Anthropic inside the same request: Claude looks the holiday up
	 * before writing, instead of relying on what it remembers. The cap bounds what one generation
	 * costs - every search is billed and its results are fed back as input tokens.
	 */
	private const array WEB_SEARCH_TOOL = ['type' => 'web_search_20260209', 'name' => 'web_search', 'max_uses' => 3];

	/**
	 * How many times a turn the server loop paused (`pause_turn`) is resumed before giving up.
	 */
	private const int MAX_CONTINUATIONS = 3;

	/**
	 * A non-streaming request sends nothing until the model is done, and the searches make that
	 * take well past the client's default idle timeout of 60 seconds.
	 */
	private const int TIMEOUT_SECONDS = 180;

	/**
	 * How many search results a description offers as its source: the model's pick and a few
	 * alternatives - two searches return about twenty, mostly noise.
	 */
	private const int MAX_SOURCES = 4;

	/** The date note is one sentence by the prompt; this only bounds a model that ignores it. */
	private const int MAX_DATE_NOTE_LENGTH = 500;

	private readonly string $promptDir;

	public function __construct(
		private readonly HttpClientInterface    $httpClient,
		private readonly EntityManagerInterface $entityManager,
		private readonly AlgorithmResolver      $algorithmResolver,
		private readonly string                 $anthropicApiKey,
		?string                                 $promptDir = null,
	)
	{
		$this->promptDir = $promptDir ?? dirname(__DIR__, 2) . '/config/prompts';
	}

	#[Route('/generate', name: 'admin_generate', methods: ['POST'])]
	public function __invoke(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true);
		if (!is_array($data)) {
			$data = [];
		}
		$type = $data['type'] ?? 'description_pl';
		$name = $data['name'] ?? null;
		$language = $data['language'] ?? null;
		$countryCode = $data['country'] ?? null;
		$isPolish = !in_array($type, ['name', 'description'], true);

		$date = $this->describeDate($data, $isPolish);
		if ($date === null || !$name) {
			return $this->json(['error' => 'A name and a date (day and month, or a floating rule) are required.'], 400);
		}

		if ($this->anthropicApiKey === '') {
			return $this->json(['error' => 'Anthropic API key is not configured.'], 500);
		}

		$defaultLanguage = $isPolish ? 'polski' : 'English';
		$internationalLabel = $isPolish ? 'międzynarodowy' : 'international';

		$countryLabel = $internationalLabel;
		if (is_string($countryCode) && $countryCode !== '' && $countryCode !== 'null') {
			$country = $this->entityManager->getRepository(Country::class)->find($countryCode);
			if ($country !== null) {
				$countryLabel = $country->englishName;
			}
		}

		$vars = [
			'{name}' => $name,
			'{date}' => $date,
			'{language}' => $language ?? $defaultLanguage,
			'{country}' => $countryLabel,
		];

		[$systemPrompt, $userPrompt, $maxTokens] = match ($type) {
			'name' => [$this->loadPrompt('name_system', $vars), $this->loadPrompt('name_user', $vars), 2048],
			'description' => [$this->loadPrompt('description_system', $vars), $this->loadPrompt('description_user', $vars), 4096],
			default => [$this->loadPrompt('description_system_pl', $vars), $this->loadPrompt('description_user_pl', $vars), 4096],
		};

		$messages = [['role' => 'user', 'content' => $userPrompt]];
		$searchResults = [];
		for ($continuation = 0; ; $continuation++) {
			$response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
				'headers' => [
					'x-api-key' => $this->anthropicApiKey,
					'anthropic-version' => '2023-06-01',
					'content-type' => 'application/json',
				],
				'json' => [
					'model' => 'claude-sonnet-4-6',
					'max_tokens' => $maxTokens,
					'system' => $systemPrompt,
					'messages' => $messages,
					'tools' => [self::WEB_SEARCH_TOOL],
				],
				'timeout' => self::TIMEOUT_SECONDS,
			]);

			$result = $response->toArray(false);

			if ($response->getStatusCode() !== 200) {
				$error = $result['error']['message'] ?? 'Unknown API error';
				return $this->json(['error' => $error], 502);
			}
			// Collected from every response, as a resumed turn may not repeat the searches it paused after.
			$searchResults += WebSearchResults::collect($result['content'] ?? []);

			if (($result['stop_reason'] ?? null) !== 'pause_turn' || $continuation >= self::MAX_CONTINUATIONS) {
				break;
			}
			// The paused turn goes back as-is and the server resumes it; the list is rebuilt rather than
			// appended to, so the roles keep alternating.
			$messages = [
				['role' => 'user', 'content' => $userPrompt],
				['role' => 'assistant', 'content' => $result['content']],
			];
		}

		$content = self::finalRun($result['content'] ?? []);
		$text = trim(implode('', array_map(fn(array $block) => (string)($block['text'] ?? ''), $content)));

		// A translated name has nowhere to keep a source; the link belongs to the description. Only the
		// Polish description checks the date: it is one per holiday, so one check is enough.
		$sources = [];
		$dateNote = null;
		if ($type !== 'name') {
			[$text, $lines] = self::splitControlLines($text);
			$sources = self::sources($searchResults, self::urlOrNull($lines['SOURCE'] ?? null));
			$dateNote = self::dateNote($lines['DATE'] ?? null);
		}
		if ($text === '') {
			return $this->json(['error' => 'The model returned no text.'], 502);
		}

		return $this->json(['result' => $text, 'sources' => $sources, 'date_note' => $dateNote]);
	}

	/**
	 * The holiday's date as the prompts put it. A fixed holiday has its day and month. A floating one
	 * has no date of its own - the page sends its rule, which goes to the model both as written (the
	 * algorithm's label and arguments, read as they are) and as the date it gives this year, so the
	 * model can compare either with what its search finds: sources describe such holidays by rule
	 * ("last Saturday of March") as often as by a day.
	 *
	 * @param array<string, mixed> $data the request body
	 * @return string|null null when the request carries neither a valid date nor a known rule
	 */
	private function describeDate(array $data, bool $polish): ?string
	{
		$day = filter_var($data['day'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]);
		$month = filter_var($data['month'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
		if ($day !== false && $month !== false) {
			if ($polish) {
				return sprintf('%d.%02d (dzień.miesiąc)', $day, $month);
			}
			return sprintf('on %d.%02d (day.month)', $day, $month);
		}

		$algorithm = Algorithm::tryFrom((string)($data['algorithm'] ?? ''));
		$args = $data['algorithm_args'] ?? null;
		if ($algorithm === null || !is_array($args)) {
			return null;
		}
		$rule = sprintf('%s %s', $algorithm->label(), json_encode($args, JSON_UNESCAPED_UNICODE));
		$year = (int)date('Y');
		try {
			$resolved = $this->algorithmResolver->resolve($algorithm, $args, $year);
		} catch (Throwable) {
			// Arguments the admin is still typing can miss a key the resolver needs; the rule alone
			// still tells the model when the holiday falls.
			$resolved = null;
		}

		if ($resolved === null) {
			if ($polish) {
				return sprintf('według reguły: %s (w %d roku reguła nie daje daty)', $rule, $year);
			}
			return sprintf('by the rule: %s (it gives no date in %d)', $rule, $year);
		}
		if ($polish) {
			return sprintf('według reguły: %s - w %d roku %d.%02d (dzień.miesiąc)', $rule, $year, $resolved['day'], $resolved['month']);
		}
		return sprintf('by the rule: %s - in %d on %d.%02d (day.month)', $rule, $year, $resolved['day'], $resolved['month']);
	}

	/**
	 * The answer is the run of text blocks after the last search. Text before a search is the model
	 * announcing what it is about to look up, and the answer itself arrives split into several
	 * blocks wherever a citation starts or ends, so the caller joins the run back together.
	 *
	 * @param list<array<string, mixed>> $content
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function finalRun(array $content): array
	{
		$run = [];
		foreach ($content as $block) {
			if (($block['type'] ?? null) !== 'text') {
				$run = [];
				continue;
			}
			$run[] = $block;
		}
		return $run;
	}

	/**
	 * Cuts off the control lines the description prompts ask for at the end of the answer, in any
	 * order - `SOURCE: <url | none>` and, in the Polish prompt, `DATE: <ok | note>` - so they never
	 * land in a description, whatever they hold.
	 *
	 * @return array{string, array<string, string>} the text without them, and their values by keyword
	 */
	private static function splitControlLines(string $text): array
	{
		$lines = [];
		while (preg_match('/(?:^|\n)[ \t]*(SOURCE|DATE):[ \t]*([^\n]*?)[ \t]*$/u', $text, $match, PREG_OFFSET_CAPTURE) === 1) {
			$lines[$match[1][0]] ??= $match[2][0];
			$text = rtrim(substr($text, 0, $match[0][1]));
		}
		return [$text, $lines];
	}

	private static function urlOrNull(?string $value): ?string
	{
		if ($value === null || !WebSearchResults::isHttpUrl($value)) {
			return null;
		}
		return $value;
	}

	/**
	 * What the model found about the date, for the admin to read - null when it says the date
	 * agrees with the sources, or says nothing. Never applied to the holiday: holiday blogs
	 * disagree, and the date stays the admin's decision.
	 */
	private static function dateNote(?string $value): ?string
	{
		if ($value === null) {
			return null;
		}
		$value = trim($value);
		if ($value === '' || strcasecmp($value, 'ok') === 0) {
			return null;
		}
		return mb_substr($value, 0, self::MAX_DATE_NOTE_LENGTH);
	}

	/**
	 * What the page offers as the description's source, for the admin to open and pick: the page
	 * the model says it relied on, only when the search really returned it, then other results in
	 * search order. A page the model names is no proof the text rests on it - hence a choice, not
	 * a value written into the field.
	 *
	 * @param array<string, string> $searchResults URL => title, see WebSearchResults::collect()
	 * @return list<array{url: string, title: string, recommended: bool}>
	 */
	private static function sources(array $searchResults, ?string $namedUrl): array
	{
		$recommended = null;
		if ($namedUrl !== null) {
			$recommended = WebSearchResults::find($searchResults, $namedUrl);
		}

		$sources = [];
		if ($recommended !== null) {
			$sources[] = ['url' => $recommended, 'title' => $searchResults[$recommended], 'recommended' => true];
		}
		foreach ($searchResults as $url => $title) {
			if (count($sources) >= self::MAX_SOURCES) {
				break;
			}
			if ($url !== $recommended) {
				$sources[] = ['url' => $url, 'title' => $title, 'recommended' => false];
			}
		}
		return $sources;
	}

	/** @param array<string, string|int> $vars */
	private function loadPrompt(string $name, array $vars): string
	{
		$path = $this->promptDir . '/' . $name . '.txt';
		$template = file_get_contents($path);

		if ($template === false) {
			throw new RuntimeException(sprintf('Prompt template not found: %s', $path));
		}

		return strtr($template, $vars);
	}
}
