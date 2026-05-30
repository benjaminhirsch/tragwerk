<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530100000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE project_domains
                ADD COLUMN placeholder VARCHAR(64) NOT NULL DEFAULT 'default'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_domains DROP COLUMN placeholder');
    }
}
