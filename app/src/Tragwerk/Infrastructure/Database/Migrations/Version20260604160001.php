<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604160001 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE email_confirmations (
                id uuid CONSTRAINT email_confirmations_pk PRIMARY KEY,
                user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token text NOT NULL UNIQUE,
                expires_at timestamp(6) NOT NULL,
                created_at timestamp(6) NOT NULL
            )',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_confirmations');
    }
}
