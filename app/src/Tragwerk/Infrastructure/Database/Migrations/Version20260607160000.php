<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607160000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE project_env_vars (
                id           VARCHAR(36)  NOT NULL,
                project_id   VARCHAR(36)  NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                branch       VARCHAR(255) NOT NULL,
                key          VARCHAR(255) NOT NULL,
                value        TEXT         NOT NULL,
                is_secret    BOOLEAN      NOT NULL DEFAULT false,
                is_inherited BOOLEAN      NOT NULL DEFAULT false,
                created_at   TIMESTAMP(6) NOT NULL,
                updated_at   TIMESTAMP(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE (project_id, branch, key)
            )
        SQL);
        $this->addSql('CREATE INDEX project_env_vars_project_branch ON project_env_vars (project_id, branch)');
        $this->addSql(
            'CREATE INDEX project_env_vars_inherited ON project_env_vars (project_id, is_inherited)'
            . ' WHERE is_inherited = true',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project_env_vars');
    }
}
