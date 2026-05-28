<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528130454 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE build_logs (
                id         VARCHAR(36)  NOT NULL PRIMARY KEY,
                project_id VARCHAR(36)  NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                branch     VARCHAR(255) NOT NULL,
                type       VARCHAR(16)  NOT NULL,
                message    TEXT         NOT NULL,
                created_at TIMESTAMP(6) NOT NULL DEFAULT NOW()
            )
            SQL);

        $this->addSql('CREATE INDEX build_logs_project_branch_idx ON build_logs (project_id, branch)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE build_logs');
    }
}
