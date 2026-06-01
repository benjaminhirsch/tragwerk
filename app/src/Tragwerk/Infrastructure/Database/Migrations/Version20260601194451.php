<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Time-series table for host-level server metrics (CPU/RAM/disk/load), sampled periodically over
 * SSH by the metrics:sample ticker. Rows are pruned by the ticker after the retention window.
 */
final class Version20260601194451 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE server_metrics (
                id               UUID        NOT NULL PRIMARY KEY,
                server_id        UUID        NOT NULL REFERENCES servers(id) ON DELETE CASCADE,
                sampled_at       TIMESTAMPTZ NOT NULL,
                cpu_percent      REAL        NOT NULL,
                mem_used_bytes   BIGINT      NOT NULL,
                mem_total_bytes  BIGINT      NOT NULL,
                disk_used_bytes  BIGINT      NOT NULL,
                disk_total_bytes BIGINT      NOT NULL,
                load1            REAL        NOT NULL
            )
            SQL);

        $this->addSql('CREATE INDEX idx_server_metrics_server_time ON server_metrics (server_id, sampled_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS server_metrics');
    }
}
