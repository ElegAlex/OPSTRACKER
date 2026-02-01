<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131220324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add colonneDatePlanifiee and colonneHoraire to Campagne for CSV date mapping';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagne ADD colonne_date_planifiee VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE campagne ADD colonne_horaire VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagne DROP colonne_date_planifiee');
        $this->addSql('ALTER TABLE campagne DROP colonne_horaire');
    }
}
