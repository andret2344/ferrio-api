<?php

namespace App\Handler;

use App\Entity\Platform;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Resolves the realDevice field. Trim happens server-side so a whitespace-only client value
 * does not silently outrank the User-Agent fallback for web.
 */
trait RealDeviceResolverTrait
{
	private readonly RequestStack $requestStack;

	/**
	 * Setter injection: a trait has no constructor, so Symfony wires the dependency
	 * via this #[Required] method instead. Called once by the container at boot — never manually.
	 */
	#[Required]
	public function setRequestStack(RequestStack $requestStack): void
	{
		$this->requestStack = $requestStack;
	}

	protected function resolveRealDevice(?Platform $platform, ?string $realDevice): ?string
	{
		$trimmed = trim($realDevice ?? '');
		if ($trimmed !== '') {
			return $trimmed;
		}
		if ($platform !== Platform::WEB) {
			return null;
		}
		$ua = $this->requestStack->getCurrentRequest()?->headers->get('User-Agent') ?? '';
		return $ua === '' ? null : substr($ua, 0, 2000);
	}
}
