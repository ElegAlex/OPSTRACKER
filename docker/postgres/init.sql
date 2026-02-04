-- docker/postgres/init.sql
-- Initialisation de la base OpsTracker

-- Extensions utiles
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";  -- Pour recherche textuelle

-- Optimisations JSONB (index GIN)
-- Les index seront créés par les migrations Doctrine

-- Message de bienvenue
DO $$
BEGIN
    RAISE NOTICE 'OpsTracker database initialized successfully!';
END $$;
