<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Demo;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;

final readonly class BacklogHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $bh = ['name' => 'Benjamin H.', 'initials' => 'BH', 'color' => 'blue'];
        $ak = ['name' => 'Alex K.', 'initials' => 'AK', 'color' => 'green'];
        $sm = ['name' => 'Sara M.', 'initials' => 'SM', 'color' => 'red'];

        $sprints = [
            [
                'label'       => 'SPRINT 3',
                'name'        => '"Into the Dark Forest"',
                'active'      => true,
                'totalPoints' => 40,
                'stories'     => [
                    [
                        'id' => '#LUM-37',
                        'type' => 'story',
                        'points' => 8,
                        'assignees' => [$sm],
                        'title' => 'As a developer I want a pixel art design system for consistent retro style',
                    ],
                    [
                        'id' => '#LUM-38',
                        'type' => 'bug',
                        'points' => 3,
                        'assignees' => [$bh],
                        'title' => 'Login session expires immediately after OAuth callback',
                    ],
                    [
                        'id' => '#LUM-39',
                        'type' => 'story',
                        'points' => 13,
                        'assignees' => [
                            $sm,
                            $ak,
                        ],
                        'title' => 'As a product owner I want to reorder the backlog via drag and drop',
                    ],
                    [
                        'id' => '#LUM-41',
                        'type' => 'task',
                        'points' => 2,
                        'assignees' => [$bh],
                        'title' => 'Update Doctrine DBAL connection config for read replicas',
                    ],
                    [
                        'id' => '#LUM-42',
                        'type' => 'story',
                        'points' => 8,
                        'assignees' => [$ak],
                        'title' => 'As a user I want to create a new project so I can start tracking my team\'s work',
                    ],
                    [
                        'id' => '#LUM-43',
                        'type' => 'story',
                        'points' => 0,
                        'assignees' => [],
                        'title' => 'As a scrum master I want to see the velocity chart for sprint planning',
                    ],
                    [
                        'id' => '#LUM-44',
                        'type' => 'chore',
                        'points' => 3,
                        'assignees' => [$bh],
                        'title' => 'Upgrade PHP to 8.5 and audit deprecation warnings',
                    ],
                    [
                        'id' => '#LUM-45',
                        'type' => 'story',
                        'points' => 5,
                        'assignees' => [],
                        'title' => 'As a team member I want to receive email notifications for story assignments',
                    ],
                ],
            ],
            [
                'label'       => 'SPRINT 4',
                'name'        => '"The Crystal Caves"',
                'active'      => false,
                'totalPoints' => 28,
                'stories'     => [
                    [
                        'id' => '#LUM-46',
                        'type' => 'story',
                        'points' => 8,
                        'assignees' => [],
                        'title' => 'As a product owner I want to manage epics and link stories to them',
                    ],
                    [
                        'id' => '#LUM-47',
                        'type' => 'story',
                        'points' => 5,
                        'assignees' => [],
                        'title' => 'As a user I want to filter the board by assignee',
                    ],
                    [
                        'id' => '#LUM-48',
                        'type' => 'story',
                        'points' => 8,
                        'assignees' => [],
                        'title' => 'As a team I want a burndown chart so we can track sprint progress visually',
                    ],
                    [
                        'id' => '#LUM-49',
                        'type' => 'task',
                        'points' => 3,
                        'assignees' => [$bh],
                        'title' => 'Implement queue-based email dispatch with retry logic',
                    ],
                    [
                        'id' => '#LUM-50',
                        'type' => 'story',
                        'points' => 4,
                        'assignees' => [],
                        'title' => 'As a user I want dark/light mode toggle with persistent preference',
                    ],
                ],
            ],
        ];

        $backlogStories = [
            [
                'id' => '#LUM-51',
                'type' => 'story',
                'points' => 5,
                'assignees' => [],
                'title' => 'As a guest I want to see a product demo so I understand the tool',
            ],
            [
                'id' => '#LUM-52',
                'type' => 'story',
                'points' => 8,
                'assignees' => [],
                'title' => 'As an admin I want to invite team members via email link',
            ],
            [
                'id' => '#LUM-53',
                'type' => 'story',
                'points' => 3,
                'assignees' => [],
                'title' => 'As a user I want to archive completed sprints',
            ],
            [
                'id' => '#LUM-54',
                'type' => 'bug',
                'points' => 2,
                'assignees' => [$bh],
                'title' => 'Story point total on backlog section does not update after reorder',
            ],
            [
                'id' => '#LUM-55',
                'type' => 'story',
                'points' => 0,
                'assignees' => [],
                'title' => 'As a product owner I want story templates for recurring work types',
            ],
            [
                'id' => '#LUM-56',
                'type' => 'epic',
                'points' => 0,
                'assignees' => [],
                'title' => 'EPIC: Integrations — Slack, GitHub, Jira import',
            ],
            [
                'id' => '#LUM-57',
                'type' => 'chore',
                'points' => 3,
                'assignees' => [$ak],
                'title' => 'Set up E2E test suite with Playwright',
            ],
        ];

        return $this->renderer->render($request, 'page::scrum/backlog', [
            'sprints'        => $sprints,
            'backlogStories' => $backlogStories,
        ]);
    }
}
