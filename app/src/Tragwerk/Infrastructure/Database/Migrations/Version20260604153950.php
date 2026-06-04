<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604153950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove Docker Swarm support; make registry_id mandatory on projects';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS project_swarm_nodes');
        $this->addSql('ALTER TABLE projects DROP COLUMN IF EXISTS swarm_enabled');
        $this->addSql('ALTER TABLE projects ALTER COLUMN registry_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE projects ALTER COLUMN registry_id DROP NOT NULL');
        $this->addSql('ALTER TABLE projects ADD COLUMN swarm_enabled BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql(
            <<<'SQL'
            CREATE TABLE project_swarm_nodes (
                project_id  UUID        NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                server_id   UUID        NOT NULL REFERENCES servers(id)  ON DELETE CASCADE,
                role        VARCHAR(20) NOT NULL DEFAULT 'worker',
                is_storage  BOOLEAN     NOT NULL DEFAULT FALSE,
                PRIMARY KEY (project_id, server_id)
            )
            SQL,
        );
    }
}
