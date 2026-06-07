<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607130000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE projects ADD COLUMN webhook_secret VARCHAR(255) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE projects DROP COLUMN webhook_secret');
    }
}
