<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint V2.1c - Notifications SMS
 * - T-2401 : Ajout champs telephone et sms_opt_in sur Agent
 */
final class Version20260124210001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sprint V2.1c - Ajout champs telephone et sms_opt_in sur Agent pour notifications SMS';
    }

    public function up(Schema $schema): void
    {
        // T-2401 : Champs SMS sur Agent
        $this->addSql('ALTER TABLE agent ADD telephone VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent ADD sms_opt_in BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent DROP COLUMN IF EXISTS telephone');
        $this->addSql('ALTER TABLE agent DROP COLUMN IF EXISTS sms_opt_in');
    }
}
