<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Application\Helper\AbbreviationHelper;

interface Abbreviation
{
    public function abbreviation(): AbbreviationHelper;
}
