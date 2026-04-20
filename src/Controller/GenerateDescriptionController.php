<?php

namespace App\Controller;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/manage/api')]
class GenerateDescriptionController extends AbstractController
{
	private readonly string $promptDir;

	public function __construct(
		private readonly HttpClientInterface $httpClient,
		private readonly string              $anthropicApiKey,
		?string                              $promptDir = null,
	)
	{
		$this->promptDir = $promptDir ?? dirname(__DIR__, 2) . '/config/prompts';
	}

	#[Route('/generate', name: 'manage_generate', methods: ['POST'])]
	public function __invoke(Request $request): JsonResponse
	{
		$data = json_decode($request->getContent(), true);
		$type = $data['type'] ?? 'description_pl';
		$day = $data['day'] ?? null;
		$month = $data['month'] ?? null;
		$name = $data['name'] ?? null;
		$language = $data['language'] ?? null;

		if (!$day || !$month || !$name) {
			return $this->json(['error' => 'Day, month and name are required.'], 400);
		}

		if ($this->anthropicApiKey === '') {
			return $this->json(['error' => 'Anthropic API key is not configured.'], 500);
		}

		$defaultLanguage = match ($type) {
			'name', 'description' => 'English',
			default => 'polski',
		};

		$vars = [
			'{name}' => $name,
			'{day}' => $day,
			'{month}' => sprintf('%02d', $month),
			'{language}' => $language ?? $defaultLanguage,
		];

		[$systemPrompt, $userPrompt, $maxTokens] = match ($type) {
			'name' => [$this->loadPrompt('name_system', $vars), $this->loadPrompt('name_user', $vars), 100],
			'description' => [$this->loadPrompt('description_system', $vars), $this->loadPrompt('description_user', $vars), 1024],
			default => [$this->loadPrompt('description_system_pl', $vars), $this->loadPrompt('description_user_pl', $vars), 1024],
		};

		$response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
			'headers' => [
				'x-api-key' => $this->anthropicApiKey,
				'anthropic-version' => '2023-06-01',
				'content-type' => 'application/json',
			],
			'json' => [
				'model' => 'claude-haiku-4-5-20251001',
				'max_tokens' => $maxTokens,
				'system' => $systemPrompt,
				'messages' => [
					['role' => 'user', 'content' => $userPrompt],
				],
			],
		]);

		$result = $response->toArray(false);

		if ($response->getStatusCode() !== 200) {
			$error = $result['error']['message'] ?? 'Unknown API error';
			return $this->json(['error' => $error], 502);
		}

		$text = trim($result['content'][0]['text'] ?? '');

		return $this->json(['result' => $text]);
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
