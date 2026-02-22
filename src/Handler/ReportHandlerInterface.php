<?php

namespace App\Handler;

interface ReportHandlerInterface
{
	public function list(string $userId): array;

	public function create(string $userId, object $payload): void;
}
