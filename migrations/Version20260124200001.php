<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 20 - Complements V1
 * - T-2005 : Ajout capaciteItJour, dureeInterventionMinutes a Campagne (RG-131)
 * - T-2006 : Ajout dureeEstimeeMinutes a TypeOperation (RG-132)
 * - T-2007 : Ajout joursVerrouillage a Campagne (RG-123)
 * - T-2003 : Creation table coordinateur_perimetre (RG-114)
 */
final class Version20260124200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sprint 20 - Capacite IT, Abaques duree, Verrouillage configurable, Coordinateur perimetre';
    }

    public function up(Schema $schema): void
    {
        // T-2005, T-2007 : Campagne - capacite IT et verrouillage
        $this->addSql('ALTER TABLE campagne ADD capacite_it_jour INT DEFAULT NULL');
        $this->addSql('ALTER TABLE campagne ADD duree_intervention_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE campagne ADD jours_verrouillage INT NOT NULL DEFAULT 2');

        // T-2006 : TypeOperation - abaques duree
        $this->addSql('ALTER TABLE type_operation ADD duree_estimee_minutes INT DEFAULT NULL');

        // T-2003 : Table coordinateur_perimetre (RG-114)
        $this->addSql('CREATE TABLE coordinateur_perimetre (
            id SERIAL PRIMARY KEY,
            coordinateur_id INT NOT NULL REFERENCES utilisateur(id) ON DELETE CASCADE,
            service VARCHAR(100) NOT NULL,
            site VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            CONSTRAINT unique_coordinateur_service UNIQUE (coordinateur_id, service)
        )');
        $this->addSql('CREATE INDEX IDX_coord_perimetre_coordinateur ON coordinateur_perimetre (coordinateur_id)');
        $this->addSql('COMMENT ON COLUMN coordinateur_perimetre.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS coordinateur_perimetre');
        $this->addSql('ALTER TABLE type_operation DROP COLUMN IF EXISTS duree_estimee_minutes');
        $this->addSql('ALTER TABLE campagne DROP COLUMN IF EXISTS capacite_it_jour');
        $this->addSql('ALTER TABLE campagne DROP COLUMN IF EXISTS duree_intervention_minutes');
        $this->addSql('ALTER TABLE campagne DROP COLUMN IF EXISTS jours_verrouillage');
    }
}
