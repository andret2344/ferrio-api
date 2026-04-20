<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/manage/api')]
class GenerateDescriptionController extends AbstractController
{
	public function __construct(
		private readonly HttpClientInterface $httpClient,
		private readonly string              $anthropicApiKey,
	)
	{
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

		[$systemPrompt, $userPrompt, $maxTokens] = match ($type) {
			'name' => $this->buildNamePrompt($name, $day, $month, $language),
			'description' => $this->buildDescriptionPrompt($name, $day, $month, $language),
			default => $this->buildDescriptionPlPrompt($name, $day, $month),
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

	/** @return array{string, string, int} */
	private function buildNamePrompt(string $name, int $day, int $month, ?string $language): array
	{
		$system = 'You are helping to find the official name of a holiday in a specific language. '
			. 'Given a holiday name in Polish and its date, provide the most commonly used name for this holiday in the target language. '
			. 'Return ONLY the name, nothing else. No quotes, no explanation.';

		$user = sprintf(
			'Holiday: "%s" (date: %d.%02d, day.month). Target language: %s.',
			$name, $day, $month, $language ?? 'English',
		);

		return [$system, $user, 100];
	}

	/** @return array{string, string, int} */
	private function buildDescriptionPrompt(string $name, int $day, int $month, ?string $language): array
	{
		$system = 'You are a writer for a holiday calendar website called Ferrio. '
			. 'Your task is to write engaging, informative descriptions of holidays and observances. '
			. 'Rules: '
			. '- Write exactly 150-250 words. '
			. '- Include: origin/history, significance, how it\'s celebrated, interesting facts. '
			. '- Tone: informative but accessible, slightly playful where appropriate. '
			. '- Do NOT start with "This holiday..." or "This day..." or equivalents in other languages. '
			. '- Do NOT use generic filler phrases. '
			. '- Return ONLY the description text, no headers, no markdown, no quotes.';

		$lang = $language ?? 'English';
		$user = sprintf(
			'Write a description for the holiday "%s" celebrated on %d.%02d (day.month). Write in %s. '
			. 'Do NOT include the holiday name, date, or any title/header. Start directly with the description text.',
			$name, $day, $month, $lang,
		);

		return [$system, $user, 1024];
	}

	/** @return array{string, string, int} */
	private function buildDescriptionPlPrompt(string $name, int $day, int $month): array
	{
		$system = 'Jesteś autorem tekstów dla strony z kalendarzem świąt Ferrio. '
			. 'Twoim zadaniem jest pisanie angażujących, informacyjnych opisów świąt i dni tematycznych. '
			. 'Zasady: '
			. '- Napisz dokładnie 150-250 słów. '
			. '- Zawrzyj: genezę/historię, znaczenie, jak jest obchodzone, ciekawostki. '
			. '- Ton: informacyjny, ale przystępny, lekko zabawny gdzie pasuje. '
			. '- NIE zaczynaj od "To święto..." ani "Ten dzień...". '
			. '- NIE używaj ogólnikowych frazesów-wypełniaczy. '
			. '- Unikaj bezpośrednich zwrotów do czytelnika (nie komplikuj odmiany przez osoby). '
			. '- Pisz po polsku. '
			. '- Zwróć TYLKO tekst opisu, bez nagłówków, bez markdownu, bez cudzysłowów.';

		$user = sprintf('Napisz opis święta "%s" obchodzonego %d.%02d (dzień.miesiąc).', $name, $day, $month);

		return [$system, $user, 1024];
	}
}
