<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration : Ajout des champs d'historique de report sur Operation.
 * - date_planifiee_initiale : memorise la date avant le premier report
 * - nombre_reports : compteur de reports
 */
final class Version20260203140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs date_planifiee_initiale et nombre_reports sur operation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation ADD date_planifiee_initiale TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE operation ADD nombre_reports SMALLINT NOT NULL DEFAULT 0');
        $this->addSql('COMMENT ON COLUMN operation.date_planifiee_initiale IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation DROP COLUMN date_planifiee_initiale');
        $this->addSql('ALTER TABLE operation DROP COLUMN nombre_reports');
    }
}
