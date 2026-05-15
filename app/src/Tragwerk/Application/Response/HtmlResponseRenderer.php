<?php

declare(strict_types=1);

namespace Tragwerk\Application\Response;

use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HtmlResponseRenderer implements ResponseRenderer
{
    public function __construct(
        private TemplateRendererInterface $templateRenderer,
    ) {
    }

    /** @inheritDoc */
    #[Override]
    public function render(
        ServerRequestInterface $request,
        string $name,
        array $params = [],
        int $statusCode = 200,
    ): ResponseInterface {
        $html = $this->templateRenderer->render($name, $params);

        return new HtmlResponse($html, $statusCode);
    }
}
