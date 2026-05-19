<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Server;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\Server as ServerEntity;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function filter_var;
use function trim;

use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

final readonly class Server implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $name,
        #[FromBody]
        public string $host,
        #[FromBody]
        public string|null $credentialId = null,
    ) {
        $errors            = [];
        $emptyFieldMessage = _('Field can\'t be empty');

        if (trim($this->name) === '') {
            $errors[] = ValidationError::make('name', $emptyFieldMessage);
        }

        if (trim($this->host) === '') {
            $errors[] = ValidationError::make('host', $emptyFieldMessage);
        }

        if (
            trim($this->host) !== ''
            && filter_var($this->host, FILTER_VALIDATE_IP, ['flags' => FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6]) === false
        ) {
            $errors[] = ValidationError::make('host', _('Invalid IP address'));
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }

    public function createServer(UserIdentifier $createdBy, ProjectIdentifier $projectId): ServerEntity
    {
        $now          = TimestampImmutable::now();
        $credentialId = $this->credentialId !== null && CredentialIdentifier::isValid($this->credentialId)
            ? CredentialIdentifier::fromString($this->credentialId)
            : null;

        return new ServerEntity(
            ServerIdentifier::create(),
            $this->name,
            $this->host,
            $credentialId,
            $projectId,
            $now,
            $createdBy,
            $now,
            $createdBy,
        );
    }
}
