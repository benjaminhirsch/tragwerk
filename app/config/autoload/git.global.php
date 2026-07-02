<?php

declare(strict_types=1);

$repositoriesPath   = getenv('TRAGWERK_REPOSITORIES_PATH') ?: 'data/repositories';
$authorizedKeysPath = getenv('TRAGWERK_AUTHORIZED_KEYS_PATH') ?: 'data/ssh/authorized_keys';
$sshHost            = getenv('TRAGWERK_SSH_HOST') ?: 'tragwerk-git';
$sshRepoBase        = getenv('TRAGWERK_SSH_REPO_BASE') ?: 'repos';
$appInternalUrl     = getenv('TRAGWERK_APP_INTERNAL_URL') ?: 'http://app';

return [
    'git' => [
        'repositories_path'    => $repositoriesPath,
        'authorized_keys_path' => $authorizedKeysPath,
        'ssh_host'             => $sshHost,
        'ssh_repo_base'        => $sshRepoBase,
        'app_internal_url'     => $appInternalUrl,
    ],
];
