<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum ServiceRuntime: string
{
    // MariaDB
    case MARIADB106  = 'mariadb:10.6';
    case MARIADB1011 = 'mariadb:10.11';
    case MARIADB114  = 'mariadb:11.4';
    case MARIADB118  = 'mariadb:11.8';

    // Oracle MySQL
    case MYSQL8 = 'mysql:8';

    // PostgreSQL
    case POSTGRES14 = 'postgresql:14';
    case POSTGRES15 = 'postgresql:15';
    case POSTGRES16 = 'postgresql:16';
    case POSTGRES17 = 'postgresql:17';
    case POSTGRES18 = 'postgresql:18';

    // Redis
    case REDIS6 = 'redis:6';
    case REDIS7 = 'redis:7';
    case REDIS8 = 'redis:8';

    // Valkey
    case VALKEY8 = 'valkey:8';
    case VALKEY9 = 'valkey:9';
}
