<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Exception\ValidationError;

use function _;
use function preg_match;
use function preg_replace;

final readonly class TwoFactorEnable implements DtoInterface
{
    public string $code;

    public function __construct(
        #[FromBody]
        string $code,
    ) {
        $this->code = preg_replace('/\D/', '', $code) ?? '';

        if (preg_match('/^\d{6}$/', $this->code) !== 1) {
            throw ValidationError::make('code', _('Enter the 6-digit code from your authenticator app'));
        }
    }
}
