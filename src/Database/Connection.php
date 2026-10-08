<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class Connection
{
    public static function createFromEnvironment(): PDO
    {
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '5432';
        $name = getenv('DB_NAME') ?: 'order_api';
        $user = getenv('DB_USER') ?: 'order_api';
        $password = getenv('DB_PASSWORD') ?: 'order_api';

        return new PDO(
            "pgsql:host={$host};port={$port};dbname={$name}",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
