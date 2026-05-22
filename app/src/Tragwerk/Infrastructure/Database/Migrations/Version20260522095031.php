<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522095031 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE lock_keys ('
            . 'key_id VARCHAR(64) NOT NULL, '
            . 'key_token VARCHAR(44) NOT NULL, '
            . 'key_expiration DOUBLE PRECISION NOT NULL, '
            . 'PRIMARY KEY(key_id)'
            . ')',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lock_keys');
    }
}
