<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625211708 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE team_invitations ADD COLUMN role varchar(16) NOT NULL DEFAULT 'member'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_invitations DROP COLUMN role');
    }
}
