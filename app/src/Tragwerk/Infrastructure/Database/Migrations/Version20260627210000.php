<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-environment cron run history, reconstructed from each app's `{app}-cron` supercronic logs by
 * the cron:sample ticker. The UNIQUE key makes ingest idempotent across overlapping log windows;
 * rows are pruned by the ticker after the retention window.
 */
final class Version20260627210000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cron_runs (
                id          UUID         NOT NULL PRIMARY KEY,
                project_id  VARCHAR(36)  NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                branch      VARCHAR(255) NOT NULL,
                app_slug    VARCHAR(255) NOT NULL,
                job_name    VARCHAR(255) NOT NULL,
                command     TEXT         NOT NULL,
                schedule    VARCHAR(255) NULL,
                started_at  TIMESTAMPTZ  NOT NULL,
                finished_at TIMESTAMPTZ  NULL,
                succeeded   BOOLEAN      NULL,
                output      TEXT         NULL,
                CONSTRAINT uniq_cron_runs_run UNIQUE (project_id, branch, command, started_at)
            )
            SQL);

        $this->addSql('CREATE INDEX idx_cron_runs_env_time ON cron_runs (project_id, branch, started_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS cron_runs');
    }
}
