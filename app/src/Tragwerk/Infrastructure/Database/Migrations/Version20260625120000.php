<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260625120000 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN two_factor_confirmed_at TIMESTAMP(6) DEFAULT NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE user_two_factor (
                id           uuid         NOT NULL,
                user_id      uuid         NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                secret       TEXT         NOT NULL,
                confirmed_at TIMESTAMP(6) DEFAULT NULL,
                created_at   TIMESTAMP(6) NOT NULL,
                updated_at   TIMESTAMP(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE (user_id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE user_recovery_codes (
                id         uuid         NOT NULL,
                user_id    uuid         NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                code_hash  VARCHAR(255) NOT NULL,
                used_at    TIMESTAMP(6) DEFAULT NULL,
                created_at TIMESTAMP(6) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX user_recovery_codes_active ON user_recovery_codes (user_id, used_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE user_trusted_devices (
                id           uuid         NOT NULL,
                user_id      uuid         NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token_hash   VARCHAR(64)  NOT NULL,
                expires_at   TIMESTAMP(6) NOT NULL,
                created_at   TIMESTAMP(6) NOT NULL,
                last_used_at TIMESTAMP(6) DEFAULT NULL,
                user_agent   VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE (token_hash)
            )
        SQL);
        $this->addSql('CREATE INDEX user_trusted_devices_user ON user_trusted_devices (user_id, expires_at)');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_trusted_devices');
        $this->addSql('DROP TABLE user_recovery_codes');
        $this->addSql('DROP TABLE user_two_factor');
        $this->addSql('ALTER TABLE users DROP COLUMN two_factor_confirmed_at');
    }
}
