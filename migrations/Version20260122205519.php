<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260122205519 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RG-112: Add visibility field and authorized users relation to Campagne';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE campagne_utilisateurs_habilites (campagne_id INT NOT NULL, utilisateur_id INT NOT NULL, PRIMARY KEY (campagne_id, utilisateur_id))');
        $this->addSql('CREATE INDEX IDX_CDC7685C16227374 ON campagne_utilisateurs_habilites (campagne_id)');
        $this->addSql('CREATE INDEX IDX_CDC7685CFB88E14F ON campagne_utilisateurs_habilites (utilisateur_id)');
        $this->addSql('ALTER TABLE campagne_utilisateurs_habilites ADD CONSTRAINT FK_CDC7685C16227374 FOREIGN KEY (campagne_id) REFERENCES campagne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE campagne_utilisateurs_habilites ADD CONSTRAINT FK_CDC7685CFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE campagne ADD visibilite VARCHAR(20) NOT NULL DEFAULT \'restreinte\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagne_utilisateurs_habilites DROP CONSTRAINT FK_CDC7685C16227374');
        $this->addSql('ALTER TABLE campagne_utilisateurs_habilites DROP CONSTRAINT FK_CDC7685CFB88E14F');
        $this->addSql('DROP TABLE campagne_utilisateurs_habilites');
        $this->addSql('ALTER TABLE campagne DROP visibilite');
    }
}
