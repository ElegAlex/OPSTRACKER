<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 18 : Ajoute le champ booking_token a la table agent pour acces par token.
 */
final class Version20260124180001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ booking_token a agent pour acces reservation par token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent ADD booking_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_268B9C9D5F37A13B ON agent (booking_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_268B9C9D5F37A13B');
        $this->addSql('ALTER TABLE agent DROP booking_token');
    }
}
