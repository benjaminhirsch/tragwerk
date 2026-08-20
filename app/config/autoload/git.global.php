<?php

declare(strict_types=1);

$repositoriesPath   = getenv('TRAGWERK_REPOSITORIES_PATH') ?: 'data/repositories';
$authorizedKeysPath = getenv('TRAGWERK_AUTHORIZED_KEYS_PATH') ?: 'data/ssh/authorized_keys';
$sshHost            = getenv('TRAGWERK_SSH_HOST') ?: 'tragwerk-git';
$sshRepoBase        = getenv('TRAGWERK_SSH_REPO_BASE') ?: 'repos';
$appInternalUrl     = getenv('TRAGWERK_APP_INTERNAL_URL') ?: 'http://app';

// The bare repositories live in a volume shared with the sshd container, where
// git-receive-pack runs as the `git` user. Whenever this process writes into
// that tree as root (production: the app image declares no USER), the files
// must be handed back to that uid — git refuses repositories owned by someone
// else ("dubious ownership") and could not write into them anyway.
$repositoryUid = (int) (getenv('TRAGWERK_GIT_UID') ?: 1000);
$repositoryGid = (int) (getenv('TRAGWERK_GIT_GID') ?: 1000);

return [
    'git' => [
        'repositories_path'    => $repositoriesPath,
        'authorized_keys_path' => $authorizedKeysPath,
        'ssh_host'             => $sshHost,
        'ssh_repo_base'        => $sshRepoBase,
        'app_internal_url'     => $appInternalUrl,
        'repository_uid'       => $repositoryUid,
        'repository_gid'       => $repositoryGid,
    ],
];
