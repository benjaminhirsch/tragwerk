<?php

declare(strict_types=1);

namespace Tragwerk\Application\Mapper;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\TreeMapper;
use Psr\Http\Message\ServerRequestInterface;
use Tragwerk\Application\Exception\Validation;
use Tragwerk\Application\Validation\ValidationBag;

use function array_map;
use function is_array;

final readonly class GenericMapper
{
    public function __construct(
        private TreeMapper $mapper,
    ) {
    }

    /** @param class-string $targetClass */
    public function mapAndValidate(ServerRequestInterface $request, string $targetClass): ValidationBag
    {
        $validationMessages = null;

        try {
            // Convert empty strings to null
            $request = $request->withQueryParams(array_map(
                static fn (mixed $v) => $v === '' ? null : $v,
                $request->getQueryParams(),
            ));

            $expectedObject = $this->mapper->map($targetClass, $request);
        } catch (MappingError | Validation $e) {
            if ($e instanceof MappingError) {
                foreach ($e->messages() as $message) {
                    $validationMessages[$message->name()] = $message->body();
                }
            }

            if ($e instanceof Validation) {
                $validationMessages[$e->validationField] = $e->validationMessage;
            }
        }

        /** @var array{array-key: int|string} $passedValues */
        $passedValues = $request->getParsedBody();
        if (! is_array($passedValues)) {
            $passedValues = [];
        }

        return new ValidationBag(
            $passedValues,
            $expectedObject ?? null,
            $validationMessages ?? [],
        );
    }
}
