<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum TeamPermission: string
{
    case ViewTeam      = 'view-team';
    case EditTeam      = 'edit-team';
    case ManageMembers = 'manage-members';
    case DeleteTeam    = 'delete-team';
}
