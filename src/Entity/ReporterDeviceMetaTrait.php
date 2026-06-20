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

	/** @var array{platform: string, real_device: ?string, device_country: ?string} */
	public array $deviceMeta {
		get => [
			'platform' => $this->platform->value,
			'real_device' => $this->realDevice,
			'device_country' => $this->deviceCountry?->isoCode,
		];
	}
}
