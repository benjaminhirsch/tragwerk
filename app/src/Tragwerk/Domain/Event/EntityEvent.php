<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\Entity;

interface EntityEvent
{
    public function getEntity(): Entity;
}
