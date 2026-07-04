<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260704120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // Privilege level of the SSH user: 'root' runs setup commands directly, 'sudo' wraps them
        // in passwordless `sudo -n`. Default 'root' preserves the prior implicit assumption that
        // credentials log in as root.
        $this->addSql("ALTER TABLE credentials ADD COLUMN privilege VARCHAR(16) NOT NULL DEFAULT 'root'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credentials DROP COLUMN privilege');
    }
}
