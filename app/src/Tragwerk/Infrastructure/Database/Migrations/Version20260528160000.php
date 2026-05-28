<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528160000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE project_environments (
                id         VARCHAR(36)  NOT NULL PRIMARY KEY,
                project_id VARCHAR(36)  NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                branch     VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(6) NOT NULL DEFAULT NOW(),
                UNIQUE(project_id, branch)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project_environments');
    }
}
