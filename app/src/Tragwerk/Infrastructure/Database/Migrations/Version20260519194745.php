<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519194745 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credentials DROP COLUMN password');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credentials ADD COLUMN password text DEFAULT NULL');
    }
}
