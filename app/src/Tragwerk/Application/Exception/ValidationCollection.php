<?php

declare(strict_types=1);

namespace Tragwerk\Application\Exception;

use CuyZ\Valinor\Mapper\Tree\Message\ErrorMessage;
use DomainException;

final class ValidationCollection extends DomainException implements ErrorMessage
{
    /** @var ValidationError[] */
    public readonly array $validations;

    private function __construct(ValidationError ...$validations)
    {
        parent::__construct('Multiple validation errors');

        $this->validations = $validations;
    }

    public static function fromValidations(ValidationError ...$validations): self
    {
        return new self(...$validations);
    }

    public function body(): string
    {
        return $this->message;
    }
}
