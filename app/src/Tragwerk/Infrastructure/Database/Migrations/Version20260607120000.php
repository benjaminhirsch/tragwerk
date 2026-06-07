<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE registry_prefixes (
                id          VARCHAR(36)  NOT NULL,
                project_id  VARCHAR(36)  NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                registry_id VARCHAR(36)  NOT NULL,
                app_slug    VARCHAR(255) NOT NULL,
                branch_slug VARCHAR(255) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE (project_id, app_slug, branch_slug)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE registry_prefixes');
    }
}
