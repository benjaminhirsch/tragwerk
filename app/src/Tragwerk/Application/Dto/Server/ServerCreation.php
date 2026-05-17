<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Server;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function trim;

final readonly class ServerCreation implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $name,
        #[FromBody]
        public string $host,
    ) {
        if (trim($this->name)  === '') {
            throw ValidationError::make('name', _('Field can\'t be empty'));
        }

        if (trim($this->host)  === '') {
            throw ValidationError::make('host', _('Field can\'t be empty'));
        }
    }

    public function createServer(UserIdentifier $createdBy): Server
    {
        $now = TimestampImmutable::now();

        return new Server(
            ServerIdentifier::create(),
            $this->name,
            $this->host,
            $now,
            $createdBy,
            $now,
            $createdBy,
        );
    }
}
