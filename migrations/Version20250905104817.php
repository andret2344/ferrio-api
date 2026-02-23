<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20250905104817 extends AbstractMigration {
	#[Override]
	public function getDescription(): string {
		return 'Rename report→error, missing→suggestion with FK/index renames (no data loss)';
	}

	#[Override]
	public function up(Schema $schema): void {
		$this->addSql('RENAME TABLE missing_fixed_holiday TO fixed_holiday_suggestion');
		$this->addSql('RENAME TABLE missing_floating_holiday TO floating_holiday_suggestion');
		$this->addSql('RENAME TABLE fixed_holiday_report TO fixed_holiday_error');
		$this->addSql('RENAME TABLE floating_holiday_report TO floating_holiday_error');

		$this->addSql('ALTER TABLE fixed_holiday_suggestion DROP FOREIGN KEY FK_A2BFBED0DC9AB234');
		$this->addSql('ALTER TABLE fixed_holiday_suggestion RENAME INDEX UNIQ_A2BFBED0DC9AB234 TO UNIQ_52294C43DC9AB234');
		$this->addSql('ALTER TABLE fixed_holiday_suggestion ADD CONSTRAINT FK_52294C43DC9AB234 FOREIGN KEY (holiday) REFERENCES fixed_holiday_metadata (id)');

		$this->addSql('ALTER TABLE floating_holiday_suggestion DROP FOREIGN KEY FK_AC8AF1A3DC9AB234');
		$this->addSql('ALTER TABLE floating_holiday_suggestion RENAME INDEX UNIQ_AC8AF1A3DC9AB234 TO UNIQ_700DF3E6DC9AB234');
		$this->addSql('ALTER TABLE floating_holiday_suggestion ADD CONSTRAINT FK_700DF3E6DC9AB234 FOREIGN KEY (holiday) REFERENCES floating_holiday_metadata (id)');

		$this->addSql('ALTER TABLE fixed_holiday_error DROP FOREIGN KEY FK_6F9375A2451CDAD4');
		$this->addSql('ALTER TABLE fixed_holiday_error DROP FOREIGN KEY FK_6F9375A2DC9EE959');
		$this->addSql('ALTER TABLE fixed_holiday_error RENAME INDEX IDX_6F9375A2451CDAD4 TO IDX_30E2C35E451CDAD4');
		$this->addSql('ALTER TABLE fixed_holiday_error RENAME INDEX IDX_6F9375A2DC9EE959 TO IDX_30E2C35EDC9EE959');
		$this->addSql('ALTER TABLE fixed_holiday_error ADD CONSTRAINT FK_30E2C35E451CDAD4 FOREIGN KEY (language_code) REFERENCES language (code)');
		$this->addSql('ALTER TABLE fixed_holiday_error ADD CONSTRAINT FK_30E2C35EDC9EE959 FOREIGN KEY (metadata_id) REFERENCES fixed_holiday_metadata (id)');

		$this->addSql('ALTER TABLE floating_holiday_error DROP FOREIGN KEY FK_E892090D451CDAD4');
		$this->addSql('ALTER TABLE floating_holiday_error DROP FOREIGN KEY FK_E892090DDC9EE959');
		$this->addSql('ALTER TABLE floating_holiday_error RENAME INDEX IDX_E892090D451CDAD4 TO IDX_393DBECF451CDAD4');
		$this->addSql('ALTER TABLE floating_holiday_error RENAME INDEX IDX_E892090DDC9EE959 TO IDX_393DBECFDC9EE959');
		$this->addSql('ALTER TABLE floating_holiday_error ADD CONSTRAINT FK_393DBECF451CDAD4 FOREIGN KEY (language_code) REFERENCES language (code)');
		$this->addSql('ALTER TABLE floating_holiday_error ADD CONSTRAINT FK_393DBECFDC9EE959 FOREIGN KEY (metadata_id) REFERENCES floating_holiday_metadata (id)');
	}

	#[Override]
	public function down(Schema $schema): void {
		$this->addSql('ALTER TABLE fixed_holiday_suggestion DROP FOREIGN KEY FK_52294C43DC9AB234');
		$this->addSql('ALTER TABLE fixed_holiday_suggestion RENAME INDEX UNIQ_52294C43DC9AB234 TO UNIQ_A2BFBED0DC9AB234');
		$this->addSql('ALTER TABLE fixed_holiday_suggestion ADD CONSTRAINT FK_A2BFBED0DC9AB234 FOREIGN KEY (holiday) REFERENCES fixed_holiday_metadata (id)');

		$this->addSql('ALTER TABLE floating_holiday_suggestion DROP FOREIGN KEY FK_700DF3E6DC9AB234');
		$this->addSql('ALTER TABLE floating_holiday_suggestion RENAME INDEX UNIQ_700DF3E6DC9AB234 TO UNIQ_AC8AF1A3DC9AB234');
		$this->addSql('ALTER TABLE floating_holiday_suggestion ADD CONSTRAINT FK_AC8AF1A3DC9AB234 FOREIGN KEY (holiday) REFERENCES floating_holiday_metadata (id)');

		$this->addSql('ALTER TABLE fixed_holiday_error DROP FOREIGN KEY FK_30E2C35E451CDAD4');
		$this->addSql('ALTER TABLE fixed_holiday_error DROP FOREIGN KEY FK_30E2C35EDC9EE959');
		$this->addSql('ALTER TABLE fixed_holiday_error RENAME INDEX IDX_30E2C35E451CDAD4 TO IDX_6F9375A2451CDAD4');
		$this->addSql('ALTER TABLE fixed_holiday_error RENAME INDEX IDX_30E2C35EDC9EE959 TO IDX_6F9375A2DC9EE959');
		$this->addSql('ALTER TABLE fixed_holiday_error ADD CONSTRAINT FK_6F9375A2451CDAD4 FOREIGN KEY (language_code) REFERENCES language (code)');
		$this->addSql('ALTER TABLE fixed_holiday_error ADD CONSTRAINT FK_6F9375A2DC9EE959 FOREIGN KEY (metadata_id) REFERENCES fixed_holiday_metadata (id)');

		$this->addSql('ALTER TABLE floating_holiday_error DROP FOREIGN KEY FK_393DBECF451CDAD4');
		$this->addSql('ALTER TABLE floating_holiday_error DROP FOREIGN KEY FK_393DBECFDC9EE959');
		$this->addSql('ALTER TABLE floating_holiday_error RENAME INDEX IDX_393DBECF451CDAD4 TO IDX_E892090D451CDAD4');
		$this->addSql('ALTER TABLE floating_holiday_error RENAME INDEX IDX_393DBECFDC9EE959 TO IDX_E892090DDC9EE959');
		$this->addSql('ALTER TABLE floating_holiday_error ADD CONSTRAINT FK_E892090D451CDAD4 FOREIGN KEY (language_code) REFERENCES language (code)');
		$this->addSql('ALTER TABLE floating_holiday_error ADD CONSTRAINT FK_E892090DDC9EE959 FOREIGN KEY (metadata_id) REFERENCES floating_holiday_metadata (id)');

		$this->addSql('RENAME TABLE fixed_holiday_suggestion TO missing_fixed_holiday');
		$this->addSql('RENAME TABLE floating_holiday_suggestion TO missing_floating_holiday');
		$this->addSql('RENAME TABLE fixed_holiday_error TO fixed_holiday_report');
		$this->addSql('RENAME TABLE floating_holiday_error TO floating_holiday_report');
	}
}
