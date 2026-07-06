<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260628120000 extends AbstractMigration
{
	private const TABLES = [
		'fixed_holiday_suggestion',
		'floating_holiday_suggestion',
		'fixed_holiday_error',
		'floating_holiday_error',
	];

	#[Override]
	public function getDescription(): string
	{
		return 'Add reporter device metadata (os_version, app_version) to report tables and create api_hit traffic counter table';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		foreach (self::TABLES as $table) {
			$this->addSql(sprintf('ALTER TABLE %s ADD os_version VARCHAR(64) DEFAULT NULL, ADD app_version VARCHAR(64) DEFAULT NULL', $table));
		}
		$this->addSql('CREATE TABLE api_hit (
            bucket_hour DATETIME NOT NULL,
            version VARCHAR(8) NOT NULL,
            hits INT UNSIGNED DEFAULT 0 NOT NULL,
            PRIMARY KEY(bucket_hour, version)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
	}

	#[Override]
	public function down(Schema $schema): void
	{
		$this->addSql('DROP TABLE api_hit');
		foreach (self::TABLES as $table) {
			$this->addSql(sprintf('ALTER TABLE %s DROP COLUMN os_version, DROP COLUMN app_version', $table));
		}
	}
}
