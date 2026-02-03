<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration : Ajout du champ saisie_temps_activee sur Campagne.
 * Permet d'activer/desactiver la saisie du temps d'intervention par campagne.
 */
final class Version20260203120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ saisie_temps_activee sur la table campagne';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE campagne ADD saisie_temps_activee BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE campagne DROP COLUMN saisie_temps_activee');
    }
}
