<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait ReporterDeviceMetaTrait
{
	#[ORM\Column(type: 'string', enumType: Platform::class, options: ['default' => 'unknown'])]
	private(set) Platform $platform = Platform::UNKNOWN;

	#[ORM\Column(type: 'text', nullable: true)]
	private(set) ?string $realDevice = null;

	// Telemetry, not a domain reference: the reporter's device locale can name any ISO-3166 code,
	// including ones no holiday uses. An FK to `country` would silently null those out.
	#[ORM\Column(name: 'device_country', type: 'string', length: 2, nullable: true)]
	private(set) ?string $deviceCountry = null;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private(set) ?string $osVersion = null;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private(set) ?string $appVersion = null;

	#[ORM\Column(type: 'integer', nullable: true)]
	private(set) ?int $appBuild = null;

	/** @var array{platform: string, model: ?string, country: ?string, os_version: ?string, app_version: ?string, app_build: ?int} */
	public array $deviceMeta {
		get => [
			'platform' => $this->platform->value,
			'model' => $this->realDevice,
			'country' => $this->deviceCountry,
			'os_version' => $this->osVersion,
			'app_version' => $this->appVersion,
			'app_build' => $this->appBuild,
		];
	}
}
