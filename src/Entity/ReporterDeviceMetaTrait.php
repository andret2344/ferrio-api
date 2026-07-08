<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait ReporterDeviceMetaTrait
{
	#[ORM\Column(type: 'string', enumType: Platform::class, options: ['default' => 'unknown'])]
	private(set) Platform $platform = Platform::UNKNOWN;

	#[ORM\Column(type: 'text', nullable: true)]
	private(set) ?string $realDevice = null;

	#[ORM\ManyToOne(targetEntity: Country::class)]
	#[ORM\JoinColumn(name: 'device_country', referencedColumnName: 'iso_code', nullable: true, onDelete: 'SET NULL')]
	private(set) ?Country $deviceCountry = null;

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
			'country' => $this->deviceCountry?->isoCode,
			'os_version' => $this->osVersion,
			'app_version' => $this->appVersion,
			'app_build' => $this->appBuild,
		];
	}
}
