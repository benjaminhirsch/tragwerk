<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525220431 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE projects (
                id          VARCHAR(36)  NOT NULL,
                name        VARCHAR(255) NOT NULL,
                server_id   VARCHAR(36)  NOT NULL,
                team_id     VARCHAR(36)  NOT NULL,
                created_at  TIMESTAMP(6) NOT NULL,
                created_by  VARCHAR(36)  NOT NULL,
                updated_at  TIMESTAMP(6) NOT NULL,
                updated_by  VARCHAR(36)  NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE projects');
    }
}
