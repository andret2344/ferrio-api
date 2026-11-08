<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Tests\Fixture\AdminUserFixture;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GenerateDescriptionControllerTest extends WebTestCase
{
	private ?array $capturedBody = null;

	/** @var list<array<string, mixed>> */
	private array $capturedBodies = [];

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();
		$this->capturedBody = null;
		$this->capturedBodies = [];
	}

	public function testReturnsDescriptionPl(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(
			self::answer('Opis świąteczny.'),
			['http_code' => 200],
		));

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 25, 'month' => 12, 'name' => 'Boże Narodzenie']));

		$this->assertResponseIsSuccessful();
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Opis świąteczny.', $data['result']);

		$this->assertSame('claude-sonnet-4-6', $this->capturedBody['model']);
		$this->assertSame(4096, $this->capturedBody['max_tokens']);
		$this->assertSame('web_search', $this->capturedBody['tools'][0]['name']);
		$this->assertStringContainsString('Boże Narodzenie', $this->capturedBody['messages'][0]['content']);
		$this->assertStringContainsString('25.12', $this->capturedBody['messages'][0]['content']);
		$this->assertStringContainsString('Ferrio', $this->capturedBody['system']);
	}

	public function testReturnsDescriptionInLanguage(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(
			self::answer('A holiday description.'),
			['http_code' => 200],
		));

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 1, 'month' => 1, 'name' => 'Nowy Rok', 'type' => 'description', 'language' => 'German']));

		$this->assertResponseIsSuccessful();
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('A holiday description.', $data['result']);

		$this->assertSame(4096, $this->capturedBody['max_tokens']);
		$this->assertStringContainsString('German', $this->capturedBody['messages'][0]['content']);
		$this->assertStringContainsString('Nowy Rok', $this->capturedBody['messages'][0]['content']);
	}

	public function testReturnsTranslatedName(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(
			self::answer('Christmas'),
			['http_code' => 200],
		));

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 25, 'month' => 12, 'name' => 'Boże Narodzenie', 'type' => 'name', 'language' => 'English']));

		$this->assertResponseIsSuccessful();
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Christmas', $data['result']);

		$this->assertSame(2048, $this->capturedBody['max_tokens']);
		$this->assertSame('web_search', $this->capturedBody['tools'][0]['name']);
		$this->assertStringContainsString('English', $this->capturedBody['messages'][0]['content']);
	}

	public function testReturnsBadRequestWhenFieldsMissing(): void
	{
		$client = $this->createAuthenticatedClient();

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 25, 'month' => 12]));

		$this->assertResponseStatusCodeSame(400);
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertArrayHasKey('error', $data);
	}

	public function testReturns502OnApiError(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(
			json_encode(['error' => ['message' => 'Rate limited']]),
			['http_code' => 429],
		));

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 1, 'month' => 1, 'name' => 'Nowy Rok']));

		$this->assertResponseStatusCodeSame(502);
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Rate limited', $data['error']);
	}

	public function testDefaultTypeFallsToDescriptionPl(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(
			self::answer('Opis.'),
			['http_code' => 200],
		));

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 1, 'month' => 5, 'name' => 'Święto Pracy', 'type' => 'unknown_type']));

		$this->assertResponseIsSuccessful();

		$this->assertStringContainsString('Ferrio', $this->capturedBody['system']);
		$this->assertStringContainsString('polsku', $this->capturedBody['system']);
	}

	public function testRequiresAuthentication(): void
	{
		$client = static::createClient();

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 1, 'month' => 1, 'name' => 'Nowy Rok']));

		// With form_login there is no Basic Auth challenge: the entry point redirects to the login form at `/`.
		$this->assertResponseRedirects('http://localhost/');
	}

	public function testReturnsOnlyTheTextAfterTheLastSearch(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(json_encode([
			'stop_reason' => 'end_turn',
			'content' => [
				['type' => 'text', 'text' => 'Let me look this holiday up.'],
				['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'web_search', 'input' => ['query' => 'Dzień Kota']],
				['type' => 'web_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => []],
				['type' => 'text', 'text' => 'To święto '],
				['type' => 'text', 'text' => 'obchodzą miłośnicy kotów.', 'citations' => [['type' => 'web_search_result_location']]],
			],
		]), ['http_code' => 200]));

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 17, 'month' => 2, 'name' => 'Dzień Kota']));

		$this->assertResponseIsSuccessful();
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('To święto obchodzą miłośnicy kotów.', $data['result']);
	}

	public function testResumesAPausedTurn(): void
	{
		$paused = [
			['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'web_search', 'input' => ['query' => 'Nowy Rok']],
			self::searchResults('https://a.example.org'),
		];
		$client = $this->createAuthenticatedClient(
			new MockResponse(json_encode(['stop_reason' => 'pause_turn', 'content' => $paused]), ['http_code' => 200]),
			new MockResponse(self::answer("Opis.\nSOURCE: https://a.example.org"), ['http_code' => 200]),
		);

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 1, 'month' => 1, 'name' => 'Nowy Rok']));

		$this->assertResponseIsSuccessful();
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Opis.', $data['result']);
		// The search ran before the pause, and the resumed turn does not repeat it.
		$this->assertSame([['url' => 'https://a.example.org', 'title' => 'Page', 'recommended' => true]], $data['sources']);
		$this->assertCount(2, $this->capturedBodies);
		$messages = $this->capturedBodies[1]['messages'];
		$this->assertSame(['user', 'assistant'], array_column($messages, 'role'));
		$this->assertSame($paused, $messages[1]['content']);
	}

	public function testReturns502WhenNoTextCameBack(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(json_encode([
			'stop_reason' => 'max_tokens',
			'content' => [
				['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'web_search', 'input' => ['query' => 'Nowy Rok']],
			],
		]), ['http_code' => 200]));

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 1, 'month' => 1, 'name' => 'Nowy Rok']));

		$this->assertResponseStatusCodeSame(502);
	}

	/**
	 * The search tool filters its results through code execution and the answer carries no
	 * citations, so the sources come from the result blocks and the model's own SOURCE line.
	 */
	public function testOffersTheNamedSourceFirstThenOtherResults(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(json_encode([
			'stop_reason' => 'end_turn',
			'content' => [
				self::searchResults('https://a.example.org', 'https://b.example.org', 'https://c.example.org'),
				['type' => 'code_execution_tool_result', 'content' => []],
				self::searchResults('https://b.example.org', 'https://d.example.org', 'https://e.example.org'),
				['type' => 'text', 'text' => "Raz dwa trzy.\nSOURCE: https://c.example.org/"],
			],
		]), ['http_code' => 200]));

		$this->generate($client, 'description_pl');

		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Raz dwa trzy.', $data['result']);
		$this->assertSame([
			// Named with a trailing slash the search result does not have.
			['url' => 'https://c.example.org', 'title' => 'Page', 'recommended' => true],
			['url' => 'https://a.example.org', 'title' => 'Page', 'recommended' => false],
			['url' => 'https://b.example.org', 'title' => 'Page', 'recommended' => false],
			['url' => 'https://d.example.org', 'title' => 'Page', 'recommended' => false],
		], $data['sources']);
	}

	public function testANamedSourceTheSearchNeverReturnedIsNotRecommended(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(json_encode([
			'stop_reason' => 'end_turn',
			'content' => [
				self::searchResults('https://a.example.org'),
				['type' => 'web_search_tool_result', 'content' => ['type' => 'web_search_tool_result_error', 'error_code' => 'max_uses_exceeded']],
				['type' => 'text', 'text' => "Opis.\nSOURCE: https://made-up.example.org"],
			],
		]), ['http_code' => 200]));

		$this->generate($client, 'description');

		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Opis.', $data['result']);
		$this->assertSame([['url' => 'https://a.example.org', 'title' => 'Page', 'recommended' => false]], $data['sources']);
	}

	/**
	 * A floating holiday has no day and month of its own; before the rule was sent, every one of them
	 * went to the model as "1.01".
	 */
	public function testFloatingHolidaySendsItsRuleAndThisYearsDate(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(self::answer('Opis.'), ['http_code' => 200]));

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode([
			'name' => 'Święto Dziękczynienia',
			'algorithm' => 'nth_day_of_week_in_month',
			'algorithm_args' => ['nth' => 4, 'dayOfWeek' => 4, 'month' => 11],
		]));

		$this->assertResponseIsSuccessful();
		$prompt = $this->capturedBody['messages'][0]['content'];
		$this->assertStringContainsString('N-th day of week in month {"nth":4,"dayOfWeek":4,"month":11}', $prompt);
		$this->assertMatchesRegularExpression('/w \d{4} roku \d{1,2}\.11 \(dzień\.miesiąc\)/', $prompt);
		$this->assertStringNotContainsString('{date}', $prompt);
	}

	public function testRuleWithoutADateThisYearStillSendsTheRule(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(self::answer('Opis.'), ['http_code' => 200]));

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode([
			'name' => 'Jakieś święto',
			'type' => 'description',
			'algorithm' => 'hardcoded_dates',
			'algorithm_args' => ['1999' => '1.1'],
		]));

		$this->assertResponseIsSuccessful();
		$this->assertMatchesRegularExpression('/by the rule: Hardcoded dates \{"1999":"1\.1"\} \(it gives no date in \d{4}\)/', $this->capturedBody['messages'][0]['content']);
	}

	public function testUnknownAlgorithmIsABadRequest(): void
	{
		$client = $this->createAuthenticatedClient();

		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['name' => 'X', 'algorithm' => 'nope', 'algorithm_args' => []]));

		$this->assertResponseStatusCodeSame(400);
	}

	public function testADateDisagreementComesBackAsANoteAndIsCutOffTheText(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(
			self::answer("Opis.\nDATE: Źródła podają 15.04, datę ustanowiła fundacja X.\nSOURCE: none"),
			['http_code' => 200],
		));

		$this->generate($client, 'description_pl');

		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Opis.', $data['result']);
		$this->assertSame('Źródła podają 15.04, datę ustanowiła fundacja X.', $data['date_note']);
	}

	public function testAnAgreeingDateGivesNoNote(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(self::answer("Opis.\nSOURCE: none\nDATE: OK"), ['http_code' => 200]));

		$this->generate($client, 'description_pl');

		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Opis.', $data['result']);
		$this->assertNull($data['date_note']);
	}

	public function testSourceNoneIsCutOffTheText(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(self::answer("Opis.\n\nSOURCE: none"), ['http_code' => 200]));

		$this->generate($client, 'description_pl');

		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Opis.', $data['result']);
		$this->assertSame([], $data['sources']);
	}

	/**
	 * The link belongs to the description, so the pages behind a translated name are not offered.
	 */
	public function testNameGenerationOffersNoSources(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(json_encode([
			'stop_reason' => 'end_turn',
			'content' => [
				self::searchResults('https://a.example.org'),
				['type' => 'text', 'text' => 'Christmas'],
			],
		]), ['http_code' => 200]));

		$this->generate($client, 'name');

		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Christmas', $data['result']);
		$this->assertSame([], $data['sources']);
	}

	private function generate(KernelBrowser $client, string $type): void
	{
		$client->request('POST', '/admin/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 25, 'month' => 12, 'name' => 'Boże Narodzenie', 'type' => $type, 'language' => 'English']));
		$this->assertResponseIsSuccessful();
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function searchResults(string ...$urls): array
	{
		return [
			'type' => 'web_search_tool_result',
			'tool_use_id' => 'srvtoolu_1',
			'content' => array_map(fn(string $url) => ['type' => 'web_search_result', 'url' => $url, 'title' => 'Page'], $urls),
		];
	}

	private static function answer(string $text): string
	{
		return json_encode(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => $text]]]);
	}

	private function createAuthenticatedClient(MockResponse ...$mockResponses): KernelBrowser
	{
		$client = static::createClient();

		$capturedBody = &$this->capturedBody;
		$capturedBodies = &$this->capturedBodies;
		$mock = new MockHttpClient(function (string $method, string $url, array $options) use (&$mockResponses, &$capturedBody, &$capturedBodies): MockResponse {
			$capturedBody = json_decode($options['body'], true);
			$capturedBodies[] = $capturedBody;
			return array_shift($mockResponses) ?? new MockResponse('{}');
		});

		static::getContainer()->set(HttpClientInterface::class, $mock);

		$fixtures = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get()
			->loadFixtures([AdminUserFixture::class]);
		$client->loginUser($fixtures->getReferenceRepository()
			->getReference(AdminUserFixture::ADMIN, AdminUser::class));

		return $client;
	}
}
