<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration RG-015 : Supprimer les colonnes matricule et nom de la table operation.
 *
 * TOUTES les donnees metier passent maintenant par donneesPersonnalisees (JSONB).
 * Les valeurs existantes de matricule/nom sont migrees vers donneesPersonnalisees
 * avant suppression des colonnes.
 */
final class Version20260129210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RG-015: Migre matricule/nom vers donneesPersonnalisees et supprime les colonnes';
    }

    public function up(Schema $schema): void
    {
        // 1. Migrer les donnees existantes vers donneesPersonnalisees
        // Pour chaque operation avec matricule/nom, on ajoute ces valeurs au JSON
        $this->addSql("
            UPDATE operation
            SET donnees_personnalisees = COALESCE(donnees_personnalisees, '{}'::jsonb)
                || jsonb_build_object('Matricule', matricule)
                || jsonb_build_object('Nom', nom)
            WHERE matricule IS NOT NULL OR nom IS NOT NULL
        ");

        // 2. Supprimer l'index sur matricule
        $this->addSql('DROP INDEX IF EXISTS idx_operation_matricule');

        // 3. Supprimer les colonnes matricule et nom
        $this->addSql('ALTER TABLE operation DROP COLUMN IF EXISTS matricule');
        $this->addSql('ALTER TABLE operation DROP COLUMN IF EXISTS nom');
    }

    public function down(Schema $schema): void
    {
        // 1. Recreer les colonnes
        $this->addSql('ALTER TABLE operation ADD matricule VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE operation ADD nom VARCHAR(255) DEFAULT NULL');

        // 2. Restaurer les donnees depuis donneesPersonnalisees
        $this->addSql("
            UPDATE operation
            SET matricule = donnees_personnalisees->>'Matricule',
                nom = donnees_personnalisees->>'Nom'
            WHERE donnees_personnalisees IS NOT NULL
        ");

        // 3. Recreer l'index
        $this->addSql('CREATE INDEX idx_operation_matricule ON operation (matricule)');
    }
}
