<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto;

use CuyZ\Valinor\Mapper\Http\FromBody;

final readonly class PasswordResetRequest implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $email,
    ) {
    }
}
