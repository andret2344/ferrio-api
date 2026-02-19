<?php

namespace App\Tests\Trait;

use JsonException;
use Symfony\Component\BrowserKit\AbstractBrowser;

trait TestUtilTrait
{
	protected AbstractBrowser $client;

	/**
	 * @throws JsonException
	 */
	protected function request(string $method, string $path, array $params = [], array $body = [], array $headers = []): void
	{
		$url = $this->buildUrl($path, $params);
		$content = null;
		$server = ['HTTP_ACCEPT' => 'application/json'];

		foreach ($headers as $name => $value) {
			$server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
		}

		if (!empty($body) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
			$content = json_encode($body, JSON_THROW_ON_ERROR);
			$server['CONTENT_TYPE'] = 'application/json';
		}

		$this->client->request($method, $url, [], [], $server, $content);
	}

	protected function getFixture(string $id, string $class): object
	{
		return $this->fixtures->getReferenceRepository()
			->getReference($id, $class);
	}

	private function buildUrl(string $path, array $query): string
	{
		if ($query === []) {
			return $path;
		}
		return $path . (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
	}
}
