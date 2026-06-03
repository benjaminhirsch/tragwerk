<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603141302 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE projects ADD COLUMN swarm_enabled BOOLEAN NOT NULL DEFAULT FALSE');

        $this->addSql("
            CREATE TABLE project_swarm_nodes (
                project_id VARCHAR(36) NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                server_id  UUID        NOT NULL REFERENCES servers(id)  ON DELETE RESTRICT,
                role       VARCHAR(10) NOT NULL CHECK (role IN ('manager', 'worker')),
                is_storage BOOLEAN     NOT NULL DEFAULT FALSE,
                PRIMARY KEY (project_id, server_id)
            )
        ");

        $this->addSql('CREATE INDEX idx_project_swarm_nodes_project ON project_swarm_nodes (project_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS project_swarm_nodes');
        $this->addSql('ALTER TABLE projects DROP COLUMN IF EXISTS swarm_enabled');
    }
}
