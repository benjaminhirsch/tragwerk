<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Integration;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Enum\GitForge;

use function _;
use function trim;

final readonly class Integration implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $forge,
        // Optional PAT for fetching a private external repo; empty for public repos.
        #[FromBody]
        public string $accessToken = '',
    ) {
        $errors = [];

        if (GitForge::tryFrom(trim($this->forge)) === null) {
            $errors[] = ValidationError::make('forge', _('Please select a git forge'));
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }

    public function gitForge(): GitForge
    {
        return GitForge::from(trim($this->forge));
    }

    public function accessToken(): string|null
    {
        $token = trim($this->accessToken);

        return $token === '' ? null : $token;
    }
}
