<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260708120000 extends AbstractMigration
{
	private const array REPORT_TABLES = [
		'fixed_holiday_error',
		'floating_holiday_error',
		'fixed_holiday_suggestion',
		'floating_holiday_suggestion',
	];

	#[Override]
	public function getDescription(): string
	{
		return 'Add app_build column to report/suggestion tables (device metadata sent by iOS/Android apps)';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		foreach (self::REPORT_TABLES as $table) {
			$this->addSql(sprintf('ALTER TABLE %s ADD app_build INT DEFAULT NULL', $table));
		}
	}

	#[Override]
	public function down(Schema $schema): void
	{
		foreach (self::REPORT_TABLES as $table) {
			$this->addSql(sprintf('ALTER TABLE %s DROP COLUMN app_build', $table));
		}
	}
}
