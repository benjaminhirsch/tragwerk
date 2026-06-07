<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607140000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE projects DROP COLUMN webhook_secret');
        $this->addSql(<<<'SQL'
            CREATE TABLE project_webhooks (
                id         VARCHAR(36)  NOT NULL,
                project_id VARCHAR(36)  NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                forge      VARCHAR(32)  NOT NULL,
                secret     VARCHAR(255) NOT NULL,
                created_at TIMESTAMP    NOT NULL,
                PRIMARY KEY (id),
                UNIQUE (project_id, forge)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project_webhooks');
        $this->addSql('ALTER TABLE projects ADD COLUMN webhook_secret VARCHAR(255) NULL');
    }
}
