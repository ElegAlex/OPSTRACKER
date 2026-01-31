<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131200847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reservePar and reserveLe columns to operation for public booking';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE operation ADD reserve_par VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE operation ADD reserve_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE operation DROP reserve_par');
        $this->addSql('ALTER TABLE operation DROP reserve_le');
    }
}
