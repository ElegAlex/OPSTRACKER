<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Support multi-places sur Operation + table ReservationEndUser
 * - Ajout champ capacite sur Operation (default 1)
 * - Ajout champ capaciteParDefaut sur Campagne (default 1)
 * - Creation table reservation_end_user pour reservations multiples
 */
final class Version20260201100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multi-place support: Operation.capacite, Campagne.capaciteParDefaut, table reservation_end_user';
    }

    public function up(Schema $schema): void
    {
        // Ajout champ capacite sur Operation
        $this->addSql('ALTER TABLE operation ADD capacite INT DEFAULT 1 NOT NULL');

        // Ajout champ capaciteParDefaut sur Campagne
        $this->addSql('ALTER TABLE campagne ADD capacite_par_defaut INT DEFAULT 1 NOT NULL');

        // Creation table reservation_end_user
        $this->addSql('CREATE TABLE reservation_end_user (
            id SERIAL PRIMARY KEY,
            operation_id INT NOT NULL,
            identifiant VARCHAR(255) NOT NULL,
            nom_prenom VARCHAR(255) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            service VARCHAR(100) DEFAULT NULL,
            site VARCHAR(100) DEFAULT NULL,
            reserve_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            telephone VARCHAR(20) DEFAULT NULL,
            sms_opt_in BOOLEAN DEFAULT false NOT NULL,
            ics_envoye BOOLEAN DEFAULT false NOT NULL
        )');
        $this->addSql('COMMENT ON COLUMN reservation_end_user.reserve_le IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_reservation_identifiant ON reservation_end_user (identifiant)');
        $this->addSql('CREATE INDEX IDX_reservation_operation ON reservation_end_user (operation_id)');
        $this->addSql('ALTER TABLE reservation_end_user ADD CONSTRAINT FK_reservation_operation FOREIGN KEY (operation_id) REFERENCES operation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation_end_user DROP CONSTRAINT FK_reservation_operation');
        $this->addSql('DROP TABLE reservation_end_user');
        $this->addSql('ALTER TABLE operation DROP capacite');
        $this->addSql('ALTER TABLE campagne DROP capacite_par_defaut');
    }
}
