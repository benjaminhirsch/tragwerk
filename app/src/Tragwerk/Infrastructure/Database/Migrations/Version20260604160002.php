<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604160002 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE password_resets (
                id uuid CONSTRAINT password_resets_pk PRIMARY KEY,
                user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token text NOT NULL UNIQUE,
                expires_at timestamp(6) NOT NULL,
                created_at timestamp(6) NOT NULL,
                used_at timestamp(6) NULL
            )',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_resets');
    }
}
