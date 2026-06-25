<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260625140000 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN locale VARCHAR(5) DEFAULT NULL');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN locale');
    }
}
