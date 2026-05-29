<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529200000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE project_domains (
                id         VARCHAR(36)  NOT NULL PRIMARY KEY,
                project_id VARCHAR(36)  NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                host       VARCHAR(255) NOT NULL,
                is_primary BOOLEAN      NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP(6) NOT NULL DEFAULT NOW(),
                UNIQUE(project_id, host)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project_domains');
    }
}
