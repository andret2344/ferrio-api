<?php

namespace App\Handler;

use App\Entity\Platform;

/**
 * Resolves the realDevice field. Trim happens server-side so a whitespace-only client value
 * does not silently outrank the User-Agent fallback for web.
 *
 * @property-read \Symfony\Component\HttpFoundation\RequestStack $requestStack
 */
trait RealDeviceResolverTrait
{
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
