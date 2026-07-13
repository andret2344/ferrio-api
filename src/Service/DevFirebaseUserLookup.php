<?php

namespace App\Service;

use Override;

/**
 * Local-dev stand-in for the Firebase Admin SDK: there are no Firebase credentials on a dev box, so
 * the real lookup resolves nothing and every reporter shows up as a bare UID. This maps the UIDs that
 * exist in the local database to made-up names and e-mails, purely so the admin UI has something
 * human to render. Unknown UIDs keep the production behaviour (no name, no e-mail, identicon avatar).
 */
class DevFirebaseUserLookup extends FirebaseUserLookup
{
	/** @var array<string, array{name: string, email: string}> */
	private const array USERS = [
		'NIdKC0G1NGYJL8tmDfqP4OxmCcO2' => ['name' => 'Anna Kowalska', 'email' => 'anna.kowalska@example.com'],
		'eaytGYWQVmgQ7TNcjubMSRgEY7R2' => ['name' => 'Piotr Nowak', 'email' => 'piotr.nowak@example.com'],
		'CcQHvC3PHuc6JaIEkSmo6wzlUMC3' => ['name' => 'Maria Wiśniewska', 'email' => 'maria.wisniewska@example.com'],
		'NWsjU4VNtZbsC0GPVSxmVbCFgSi2' => ['name' => 'Tomasz Lewandowski', 'email' => 'tomasz.lewandowski@example.com'],
	];

	public function __construct()
	{
		// empty — no Firebase Auth client in dev
	}

	#[Override]
	public function lookup(array $uids): array
	{
		$result = [];
		foreach (array_unique(array_filter($uids)) as $uid) {
			$result[$uid] = [
				'email' => self::USERS[$uid]['email'] ?? null,
				'name' => self::USERS[$uid]['name'] ?? null,
				'avatar' => sprintf('https://gravatar.com/avatar/%s?d=identicon', hash('sha256', $uid)),
			];
		}
		return $result;
	}
}
