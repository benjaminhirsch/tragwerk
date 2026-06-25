<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Enum\Locale;

use function _;
use function trim;

final readonly class UpdateLanguage implements DtoInterface
{
    public Locale|null $locale;

    public function __construct(
        #[FromBody]
        string $locale,
    ) {
        $locale = trim($locale);

        // An empty selection means "automatic" — follow the browser language.
        if ($locale === '') {
            $this->locale = null;

            return;
        }

        $resolved = Locale::tryFrom($locale);
        if ($resolved === null) {
            throw ValidationError::make('locale', _('Invalid language'));
        }

        $this->locale = $resolved;
    }
}
