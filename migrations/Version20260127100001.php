<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration vers architecture checklist retroactive.
 *
 * Ajoute checklistStructure a Campagne et etapesCochees a ChecklistInstance.
 * Migre les donnees existantes (copie templates vers campagnes, convertit progression).
 */
final class Version20260127100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration vers architecture checklist retroactive - Ajoute checklistStructure a Campagne, etapesCochees a ChecklistInstance';
    }

    public function up(Schema $schema): void
    {
        // 1. Ajouter le nouveau champ checklistStructure a campagne (JSON nullable)
        $this->addSql('ALTER TABLE campagne ADD checklist_structure JSON DEFAULT NULL');

        // 2. Ajouter le nouveau champ etapesCochees a checklist_instance
        $this->addSql('ALTER TABLE checklist_instance ADD etapes_cochees JSON DEFAULT \'{}\'');

        // 3. Rendre snapshot nullable (conserve pour retrocompatibilite)
        $this->addSql('ALTER TABLE checklist_instance ALTER COLUMN snapshot DROP NOT NULL');
        $this->addSql('ALTER TABLE checklist_instance ALTER COLUMN snapshot SET DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Rollback - restaurer l'ancien schema
        $this->addSql('ALTER TABLE checklist_instance ALTER COLUMN snapshot SET NOT NULL');
        $this->addSql('ALTER TABLE checklist_instance ALTER COLUMN snapshot SET DEFAULT \'{"phases": []}\'');
        $this->addSql('ALTER TABLE checklist_instance DROP COLUMN etapes_cochees');
        $this->addSql('ALTER TABLE campagne DROP COLUMN checklist_structure');
    }
}
