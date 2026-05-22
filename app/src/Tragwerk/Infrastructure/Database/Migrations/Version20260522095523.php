<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522095523 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lock_keys ALTER COLUMN key_expiration TYPE DOUBLE PRECISION USING 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE lock_keys ALTER COLUMN key_expiration TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING NOW()',
        );
    }
}
