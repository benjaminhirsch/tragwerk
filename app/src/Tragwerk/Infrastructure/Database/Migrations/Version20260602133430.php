<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Time-series table for per-environment FrankenPHP + Caddy HTTP metrics, sampled over SSH by the
 * metrics:sample ticker. Gauges are stored as-is; HTTP counters are cumulative and turned into
 * rates/avg-latency at query time. Rows are pruned by the ticker after the retention window.
 */
final class Version20260602133430 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE app_metrics (
                id              UUID         NOT NULL PRIMARY KEY,
                project_id      VARCHAR(36)  NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                branch          VARCHAR(255) NOT NULL,
                sampled_at      TIMESTAMPTZ  NOT NULL,
                total_workers   INT          NOT NULL,
                busy_workers    INT          NOT NULL,
                ready_workers   INT          NOT NULL,
                queue_depth     INT          NOT NULL,
                total_threads   INT          NOT NULL,
                busy_threads    INT          NOT NULL,
                requests_total  BIGINT       NOT NULL,
                requests_5xx    BIGINT       NOT NULL,
                requests_4xx    BIGINT       NOT NULL,
                duration_sum_ms BIGINT       NOT NULL,
                duration_count  BIGINT       NOT NULL,
                in_flight       INT          NOT NULL
            )
            SQL);

        $this->addSql('CREATE INDEX idx_app_metrics_env_time ON app_metrics (project_id, branch, sampled_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_metrics');
    }
}
