<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260709120000 extends AbstractMigration
{
	private const array TABLES = [
		'fixed_holiday_suggestion',
		'floating_holiday_suggestion',
		'fixed_holiday_error',
		'floating_holiday_error',
	];

	/** @var array<string, string> table => foreign key name used when restoring in down() */
	private const array RESTORE_FK_NAMES = [
		'fixed_holiday_suggestion' => 'FK_FHS_DEVICE_COUNTRY',
		'floating_holiday_suggestion' => 'FK_FLHS_DEVICE_COUNTRY',
		'fixed_holiday_error' => 'FK_FHE_DEVICE_COUNTRY',
		'floating_holiday_error' => 'FK_FLHE_DEVICE_COUNTRY',
	];

	#[Override]
	public function getDescription(): string
	{
		return 'Drop device_country foreign keys on report tables: reporter device locale is telemetry and may name any ISO-3166 code, not only countries that have holidays';
	}

	#[Override]
	public function up(Schema $schema): void
	{
		foreach (self::TABLES as $table) {
			// The constraint name is not assumed: it depends on whether this schema was built by
			// Version20260619120000 (explicit names) or by doctrine:schema:update (hashed names).
			foreach ($this->deviceCountryForeignKeys($table) as $fk) {
				$this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $fk));

				// InnoDB backs every foreign key with an index, creating one named after the
				// constraint when no usable index exists yet. DROP FOREIGN KEY leaves it behind,
				// and it is redundant with idx_*_device_country, which the same migration added.
				if ($this->indexExists($table, $fk)) {
					$this->addSql(sprintf('DROP INDEX %s ON %s', $fk, $table));
				}
			}
		}
	}

	#[Override]
	public function down(Schema $schema): void
	{
		foreach (self::RESTORE_FK_NAMES as $table => $fk) {
			// Codes recorded while the constraint was absent may name countries missing from
			// `country`; the foreign key cannot be restored over them, and the old schema had
			// nowhere to keep them anyway.
			$this->addSql(sprintf(
				'UPDATE %s SET device_country = NULL WHERE device_country IS NOT NULL AND device_country NOT IN (SELECT iso_code FROM country)',
				$table
			));
			$this->addSql(sprintf(
				'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (device_country) REFERENCES country (iso_code) ON DELETE SET NULL',
				$table,
				$fk
			));
		}
	}

	/** @return list<string> */
	private function deviceCountryForeignKeys(string $table): array
	{
		return $this->connection->fetchFirstColumn(
			'SELECT DISTINCT CONSTRAINT_NAME
			 FROM information_schema.KEY_COLUMN_USAGE
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = ?
			   AND COLUMN_NAME = ?
			   AND REFERENCED_TABLE_NAME IS NOT NULL',
			[$table, 'device_country']
		);
	}

	private function indexExists(string $table, string $index): bool
	{
		$count = $this->connection->fetchOne(
			'SELECT COUNT(*)
			 FROM information_schema.STATISTICS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = ?
			   AND INDEX_NAME = ?',
			[$table, $index]
		);
		return (int)$count > 0;
	}
}
