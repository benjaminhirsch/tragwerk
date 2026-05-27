<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527185224 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ssh_keys (
            id         VARCHAR(36)  NOT NULL,
            user_id    VARCHAR(36)  NOT NULL,
            name       VARCHAR(255) NOT NULL,
            public_key TEXT         NOT NULL,
            created_at TIMESTAMP(6) NOT NULL,
            PRIMARY KEY (id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ssh_keys');
    }
}
