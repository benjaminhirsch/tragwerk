<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // Optional access token for the forge-webhook flow: authenticates the
        // git fetch of private external repositories into the local bare repo.
        $this->addSql('ALTER TABLE project_webhooks ADD COLUMN access_token VARCHAR(255) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_webhooks DROP COLUMN access_token');
    }
}
