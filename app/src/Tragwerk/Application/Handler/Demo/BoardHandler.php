<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Demo;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;

final readonly class BoardHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $stories = [
            [
                'id' => '#LUM-42',
                'status' => 'todo',
                'type' => 'story',
                'priority' => 'high',
                'points' => 8,
                'blocked' => false,
                'title' => 'As a user I want to create a new project so I can start tracking my team\'s work',
                'assignees' => [['name' => 'Alex K.', 'initials' => 'AK', 'color' => 'green']],
            ],
            [
                'id' => '#LUM-38',
                'status' => 'todo',
                'type' => 'bug',
                'priority' => 'high',
                'points' => 3,
                'blocked' => true,
                'title' => 'Login session expires immediately after OAuth callback',
                'assignees' => [['name' => 'Benjamin H.', 'initials' => 'BH', 'color' => 'blue']],
            ],
            [
                'id' => '#LUM-45',
                'status' => 'todo',
                'type' => 'story',
                'priority' => 'medium',
                'points' => 5,
                'blocked' => false,
                'title' => 'As a scrum master I want to see the velocity chart so I can plan upcoming sprints',
                'assignees' => [],
            ],
            [
                'id' => '#LUM-39',
                'status' => 'inprogress',
                'type' => 'story',
                'priority' => 'high',
                'points' => 13,
                'blocked' => false,
                'title' => 'As a product owner I want to reorder the backlog via drag and drop',
                'assignees' => [
                    ['name' => 'Sara M.', 'initials' => 'SM', 'color' => 'red'],
                    ['name' => 'Alex K.', 'initials' => 'AK', 'color' => 'green'],
                ],
            ],
            [
                'id' => '#LUM-41',
                'status' => 'inprogress',
                'type' => 'task',
                'priority' => 'medium',
                'points' => 2,
                'blocked' => false,
                'title' => 'Update Doctrine DBAL connection config for read replicas',
                'assignees' => [['name' => 'Benjamin H.', 'initials' => 'BH', 'color' => 'blue']],
            ],
            [
                'id' => '#LUM-37',
                'status' => 'review',
                'type' => 'story',
                'priority' => 'high',
                'points' => 8,
                'blocked' => false,
                'title' => 'As a developer I want a pixel art design system so the UI has consistent retro style',
                'assignees' => [['name' => 'Sara M.', 'initials' => 'SM', 'color' => 'red']],
            ],
            [
                'id' => '#LUM-29',
                'status' => 'done',
                'type' => 'story',
                'priority' => 'high',
                'points' => 5,
                'blocked' => false,
                'title' => 'As a user I want to register via email so I can access the platform',
                'assignees' => [['name' => 'Benjamin H.', 'initials' => 'BH', 'color' => 'blue']],
            ],
            [
                'id' => '#LUM-30',
                'status' => 'done',
                'type' => 'story',
                'priority' => 'high',
                'points' => 3,
                'blocked' => false,
                'title' => 'As a user I want to log in via email/password',
                'assignees' => [['name' => 'Benjamin H.', 'initials' => 'BH', 'color' => 'blue']],
            ],
            [
                'id' => '#LUM-31',
                'status' => 'done',
                'type' => 'task',
                'priority' => 'medium',
                'points' => 2,
                'blocked' => false,
                'title' => 'Set up RoadRunner HTTP server with Docker',
                'assignees' => [['name' => 'Alex K.', 'initials' => 'AK', 'color' => 'green']],
            ],
            [
                'id' => '#LUM-33',
                'status' => 'done',
                'type' => 'bug',
                'priority' => 'medium',
                'points' => 1,
                'blocked' => false,
                'title' => 'CSRF token validation fails on first page load after cache clear',
                'assignees' => [['name' => 'Sara M.', 'initials' => 'SM', 'color' => 'red']],
            ],
            [
                'id' => '#LUM-35',
                'status' => 'done',
                'type' => 'chore',
                'priority' => 'low',
                'points' => 3,
                'blocked' => false,
                'title' => 'Configure PHPStan level 9 with baseline and fix all violations',
                'assignees' => [['name' => 'Benjamin H.', 'initials' => 'BH', 'color' => 'blue']],
            ],
        ];

        return $this->renderer->render($request, 'page::scrum/board', ['stories' => $stories]);
    }
}
