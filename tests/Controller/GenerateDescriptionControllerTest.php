<?php

namespace App\Tests\Controller;

use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GenerateDescriptionControllerTest extends WebTestCase
{
	private const CREDENTIALS = ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin'];

	private ?array $capturedBody = null;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();
		$this->capturedBody = null;
	}

	public function testReturnsDescriptionPl(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(
			json_encode(['content' => [['text' => 'Opis świąteczny.']]]),
			['http_code' => 200],
		));

		$client->request('POST', '/manage/api/generate', [], [], [
			...self::CREDENTIALS,
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 25, 'month' => 12, 'name' => 'Boże Narodzenie']));

		$this->assertResponseIsSuccessful();
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Opis świąteczny.', $data['result']);

		$this->assertSame('claude-haiku-4-5-20251001', $this->capturedBody['model']);
		$this->assertSame(1024, $this->capturedBody['max_tokens']);
		$this->assertStringContainsString('Boże Narodzenie', $this->capturedBody['messages'][0]['content']);
		$this->assertStringContainsString('25.12', $this->capturedBody['messages'][0]['content']);
		$this->assertStringContainsString('Ferrio', $this->capturedBody['system']);
	}

	public function testReturnsDescriptionInLanguage(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(
			json_encode(['content' => [['text' => 'A holiday description.']]]),
			['http_code' => 200],
		));

		$client->request('POST', '/manage/api/generate', [], [], [
			...self::CREDENTIALS,
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 1, 'month' => 1, 'name' => 'Nowy Rok', 'type' => 'description', 'language' => 'German']));

		$this->assertResponseIsSuccessful();
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('A holiday description.', $data['result']);

		$this->assertSame(1024, $this->capturedBody['max_tokens']);
		$this->assertStringContainsString('German', $this->capturedBody['messages'][0]['content']);
		$this->assertStringContainsString('Nowy Rok', $this->capturedBody['messages'][0]['content']);
	}

	public function testReturnsTranslatedName(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(
			json_encode(['content' => [['text' => 'Christmas']]]),
			['http_code' => 200],
		));

		$client->request('POST', '/manage/api/generate', [], [], [
			...self::CREDENTIALS,
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 25, 'month' => 12, 'name' => 'Boże Narodzenie', 'type' => 'name', 'language' => 'English']));

		$this->assertResponseIsSuccessful();
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Christmas', $data['result']);

		$this->assertSame(100, $this->capturedBody['max_tokens']);
		$this->assertStringContainsString('English', $this->capturedBody['messages'][0]['content']);
	}

	public function testReturnsBadRequestWhenFieldsMissing(): void
	{
		$client = $this->createAuthenticatedClient();

		$client->request('POST', '/manage/api/generate', [], [], [
			...self::CREDENTIALS,
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

		$client->request('POST', '/manage/api/generate', [], [], [
			...self::CREDENTIALS,
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 1, 'month' => 1, 'name' => 'Nowy Rok']));

		$this->assertResponseStatusCodeSame(502);
		$data = json_decode($client->getResponse()->getContent(), true);
		$this->assertSame('Rate limited', $data['error']);
	}

	public function testDefaultTypeFallsToDescriptionPl(): void
	{
		$client = $this->createAuthenticatedClient(new MockResponse(
			json_encode(['content' => [['text' => 'Opis.']]]),
			['http_code' => 200],
		));

		$client->request('POST', '/manage/api/generate', [], [], [
			...self::CREDENTIALS,
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 1, 'month' => 5, 'name' => 'Święto Pracy', 'type' => 'unknown_type']));

		$this->assertResponseIsSuccessful();

		$this->assertStringContainsString('Ferrio', $this->capturedBody['system']);
		$this->assertStringContainsString('polsku', $this->capturedBody['system']);
	}

	public function testRequiresAuthentication(): void
	{
		$client = static::createClient();

		$client->request('POST', '/manage/api/generate', [], [], [
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['day' => 1, 'month' => 1, 'name' => 'Nowy Rok']));

		$this->assertResponseStatusCodeSame(401);
	}

	private function createAuthenticatedClient(?MockResponse $mockResponse = null): KernelBrowser
	{
		$client = static::createClient();

		$capturedBody = &$this->capturedBody;
		$mock = new MockHttpClient(function (string $method, string $url, array $options) use ($mockResponse, &$capturedBody): MockResponse {
			$capturedBody = json_decode($options['body'], true);
			return $mockResponse ?? new MockResponse('{}');
		});

		static::getContainer()->set(HttpClientInterface::class, $mock);

		return $client;
	}
}
