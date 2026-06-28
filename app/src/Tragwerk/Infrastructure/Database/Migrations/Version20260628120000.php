<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tracks environments (project + branch) that have been manually disabled. A row's presence means the
 * environment is paused: its containers are stopped but nothing is deleted. The row is removed when the
 * environment is (re)deployed or deleted.
 */
final class Version20260628120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE disabled_environments (
                project_id  VARCHAR(36)  NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                branch      VARCHAR(255) NOT NULL,
                disabled_at TIMESTAMPTZ  NOT NULL,
                PRIMARY KEY (project_id, branch)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS disabled_environments');
    }
}
