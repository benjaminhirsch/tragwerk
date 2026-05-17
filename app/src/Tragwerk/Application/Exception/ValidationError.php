<?php

declare(strict_types=1);

namespace Tragwerk\Application\Exception;

use CuyZ\Valinor\Mapper\Tree\Message\ErrorMessage;
use DomainException;

final class ValidationError extends DomainException implements ErrorMessage
{
    private function __construct(public string $name, public string $body)
    {
        parent::__construct($body);
    }

    public static function make(string $field, string $message): self
    {
        return new self($field, $message);
    }

    public function body(): string
    {
        return $this->message;
    }
}
