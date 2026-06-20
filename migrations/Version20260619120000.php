<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260619120000 extends AbstractMigration
{
	private const TABLE_PREFIX_MAP = [
		'fixed_holiday_suggestion' => 'fhs',
		'floating_holiday_suggestion' => 'flhs',
		'fixed_holiday_error' => 'fhe',
		'floating_holiday_error' => 'flhe',
	];

	#[Override]
	public function getDescription(): string
	{
		return 'Add platform, real_device, and device_country (reporter device metadata) to all report tables, plus filter indexes';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		foreach (array_keys(self::TABLE_PREFIX_MAP) as $table) {
			$this->addSql(sprintf('ALTER TABLE %s ADD platform VARCHAR(16) DEFAULT NULL, ADD real_device TEXT DEFAULT NULL, ADD device_country VARCHAR(2) DEFAULT NULL', $table));
		}

		$this->addSql('ALTER TABLE fixed_holiday_suggestion
			ADD CONSTRAINT FK_FHS_DEVICE_COUNTRY FOREIGN KEY (device_country) REFERENCES country (iso_code) ON DELETE SET NULL');
		$this->addSql('ALTER TABLE floating_holiday_suggestion
			ADD CONSTRAINT FK_FLHS_DEVICE_COUNTRY FOREIGN KEY (device_country) REFERENCES country (iso_code) ON DELETE SET NULL');
		$this->addSql('ALTER TABLE fixed_holiday_error
			ADD CONSTRAINT FK_FHE_DEVICE_COUNTRY FOREIGN KEY (device_country) REFERENCES country (iso_code) ON DELETE SET NULL');
		$this->addSql('ALTER TABLE floating_holiday_error
			ADD CONSTRAINT FK_FLHE_DEVICE_COUNTRY FOREIGN KEY (device_country) REFERENCES country (iso_code) ON DELETE SET NULL');

		foreach (self::TABLE_PREFIX_MAP as $table => $prefix) {
			$this->addSql(sprintf('CREATE INDEX idx_%s_platform ON %s (platform)', $prefix, $table));
			$this->addSql(sprintf('CREATE INDEX idx_%s_device_country ON %s (device_country)', $prefix, $table));
			$this->addSql(sprintf('CREATE INDEX idx_%s_report_state ON %s (report_state)', $prefix, $table));
		}
	}

	#[Override]
	public function down(Schema $schema): void
	{
		foreach (self::TABLE_PREFIX_MAP as $table => $prefix) {
			$this->addSql(sprintf('DROP INDEX idx_%s_platform ON %s', $prefix, $table));
			$this->addSql(sprintf('DROP INDEX idx_%s_device_country ON %s', $prefix, $table));
			$this->addSql(sprintf('DROP INDEX idx_%s_report_state ON %s', $prefix, $table));
		}

		$this->addSql('ALTER TABLE fixed_holiday_suggestion DROP FOREIGN KEY FK_FHS_DEVICE_COUNTRY');
		$this->addSql('ALTER TABLE floating_holiday_suggestion DROP FOREIGN KEY FK_FLHS_DEVICE_COUNTRY');
		$this->addSql('ALTER TABLE fixed_holiday_error DROP FOREIGN KEY FK_FHE_DEVICE_COUNTRY');
		$this->addSql('ALTER TABLE floating_holiday_error DROP FOREIGN KEY FK_FLHE_DEVICE_COUNTRY');

		foreach (array_keys(self::TABLE_PREFIX_MAP) as $table) {
			$this->addSql(sprintf('ALTER TABLE %s DROP COLUMN platform, DROP COLUMN real_device, DROP COLUMN device_country', $table));
		}
	}
}
