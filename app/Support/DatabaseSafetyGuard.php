<?php

namespace App\Support;

use RuntimeException;

final class DatabaseSafetyGuard
{
    /**
     * Commands that must never run against a non-testing database.
     */
    private const DANGEROUS_COMMANDS = [
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
    ];

    /**
     * Abort destructive commands unless they target a clearly isolated test database.
     */
    public static function assertCurrentCommandCanRun(?array $argv = null): void
    {
        $command = self::currentCommand($argv);

        if ($command === null || ! in_array($command, self::DANGEROUS_COMMANDS, true)) {
            return;
        }

        $environment = self::env('APP_ENV') ?? '';
        $connection = self::env('DB_CONNECTION') ?? '';
        $database = self::env('DB_DATABASE') ?? '';

        self::assertCommandCanRun($command, $environment, $connection, $database);
    }

    public static function assertCommandCanRun(string $command, string $environment, string $connection, string $database): void
    {
        if (! in_array($command, self::DANGEROUS_COMMANDS, true)) {
            return;
        }

        if (self::isSafeTestingDatabase($environment, $connection, $database)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Blocked "%s" because APP_ENV=%s and DB_DATABASE=%s are not an isolated testing database.',
            $command,
            $environment !== '' ? $environment : '(missing)',
            $database !== '' ? $database : '(missing)'
        ));
    }

    public static function isSafeTestingDatabase(string $environment, string $connection, string $database): bool
    {
        if ($environment !== 'testing') {
            return false;
        }

        if ($connection === 'sqlite' && $database === ':memory:') {
            return true;
        }

        return $database !== '' && str_ends_with($database, '_testing');
    }

    private static function currentCommand(?array $argv = null): ?string
    {
        $argv ??= $_SERVER['argv'] ?? [];

        return isset($argv[1]) ? (string) $argv[1] : null;
    }

    private static function env(string $key): ?string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return null;
        }

        return (string) $value;
    }
}
