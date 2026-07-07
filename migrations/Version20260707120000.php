<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260707120000 extends AbstractMigration
{
	private const array TABLES = [
		'fixed_holiday',
		'floating_holiday',
	];

	private const array REPORT_TABLES = [
		'fixed_holiday_error',
		'floating_holiday_error',
		'fixed_holiday_suggestion',
		'floating_holiday_suggestion',
	];

	#[Override]
	public function getDescription(): string
	{
		return 'Add ai_generated flag to holidays (mark existing AI-generated); re-key api_hit by full request path; make report platform NOT NULL DEFAULT unknown';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		foreach (self::TABLES as $table) {
			$this->addSql(sprintf('ALTER TABLE %s ADD ai_generated TINYINT(1) DEFAULT 0 NOT NULL', $table));
			$this->addSql(sprintf('UPDATE %s SET ai_generated = 1', $table));
		}

		$this->addSql('DROP TABLE api_hit');
		$this->addSql('CREATE TABLE api_hit (
            bucket_hour DATETIME NOT NULL,
            path VARCHAR(255) NOT NULL,
            hits INT UNSIGNED DEFAULT 0 NOT NULL,
            PRIMARY KEY(bucket_hour, path)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

		foreach (self::REPORT_TABLES as $table) {
			$this->addSql(sprintf("UPDATE %s SET platform = 'unknown' WHERE platform IS NULL", $table));
			$this->addSql(sprintf("ALTER TABLE %s CHANGE platform platform VARCHAR(255) DEFAULT 'unknown' NOT NULL", $table));
		}
	}

	#[Override]
	public function down(Schema $schema): void
	{
		foreach (self::REPORT_TABLES as $table) {
			$this->addSql(sprintf('ALTER TABLE %s CHANGE platform platform VARCHAR(16) DEFAULT NULL', $table));
		}

		$this->addSql('DROP TABLE api_hit');
		$this->addSql('CREATE TABLE api_hit (
            bucket_hour DATETIME NOT NULL,
            version VARCHAR(8) NOT NULL,
            hits INT UNSIGNED DEFAULT 0 NOT NULL,
            PRIMARY KEY(bucket_hour, version)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

		foreach (self::TABLES as $table) {
			$this->addSql(sprintf('ALTER TABLE %s DROP COLUMN ai_generated', $table));
		}
	}
}
