<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Exception\ValidationError;

use function _;

/** Re-authentication for sensitive two-factor actions (disable, regenerate codes). */
final readonly class ConfirmPassword implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $password,
    ) {
        if ($this->password === '') {
            throw ValidationError::make('password', _('Enter your current password'));
        }
    }
}
