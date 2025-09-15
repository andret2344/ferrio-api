<?php

namespace App\Handler;

interface ReportHandlerInterface
{
	public function list(string $userId): array;

	public function create(string $userId, array $payload): void;
}
