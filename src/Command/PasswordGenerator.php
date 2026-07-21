<?php

namespace App\Command;

/**
 * Shared by the two provisioning commands, which are the only places a password is ever minted:
 * there is no self-service password reset in the panel.
 */
class PasswordGenerator
{
	private const int LENGTH = 24;

	/** Ambiguous glyphs (0/O, 1/l/I) are left out so a password read off a terminal transcribes cleanly. */
	private const string ALPHABET = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%^&*';

	public static function generate(): string
	{
		$password = '';
		for ($i = 0; $i < self::LENGTH; $i++) {
			$password .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
		}
		return $password;
	}
}
