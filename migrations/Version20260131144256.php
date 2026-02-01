<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131144256 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs de configuration pour la reservation publique (type Doodle)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagne ADD reservation_ouverte BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE campagne ADD reservation_mode VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE campagne ADD reservation_champ_identification VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagne DROP reservation_ouverte');
        $this->addSql('ALTER TABLE campagne DROP reservation_mode');
        $this->addSql('ALTER TABLE campagne DROP reservation_champ_identification');
    }
}
