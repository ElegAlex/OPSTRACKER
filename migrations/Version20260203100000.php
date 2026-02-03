<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Ajout champs duree d'intervention sur Operation
 * - duree_intervention_minutes: duree en minutes (int nullable)
 * - duree_renseignee_le: timestamp de saisie
 * - duree_renseignee_par_id: FK vers utilisateur
 */
final class Version20260203100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add intervention duration fields: duree_intervention_minutes, duree_renseignee_le, duree_renseignee_par_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation ADD duree_intervention_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE operation ADD duree_renseignee_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE operation ADD duree_renseignee_par_id INT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN operation.duree_renseignee_le IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_duree_renseignee_par FOREIGN KEY (duree_renseignee_par_id) REFERENCES utilisateur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_operation_duree_par ON operation (duree_renseignee_par_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation DROP CONSTRAINT FK_duree_renseignee_par');
        $this->addSql('DROP INDEX IDX_operation_duree_par');
        $this->addSql('ALTER TABLE operation DROP duree_intervention_minutes');
        $this->addSql('ALTER TABLE operation DROP duree_renseignee_le');
        $this->addSql('ALTER TABLE operation DROP duree_renseignee_par_id');
    }
}
