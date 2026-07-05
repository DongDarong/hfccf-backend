<?php

namespace Tests\Unit\Support;

use App\Support\DatabaseSafetyGuard;
use RuntimeException;
use Tests\TestCase;

class DatabaseSafetyGuardTest extends TestCase
{
    public function test_allows_destructive_commands_on_isolated_testing_databases(): void
    {
        DatabaseSafetyGuard::assertCommandCanRun('migrate:fresh', 'testing', 'mysql', 'hfccf_backend_testing');
        DatabaseSafetyGuard::assertCommandCanRun('db:wipe', 'testing', 'sqlite', ':memory:');

        $this->assertTrue(true);
    }

    public function test_blocks_destructive_commands_on_non_testing_databases(): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseSafetyGuard::assertCommandCanRun('migrate:fresh', 'local', 'mysql', 'hfccf_backend');
    }

    public function test_blocks_destructive_commands_when_testing_database_name_is_not_isolated(): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseSafetyGuard::assertCommandCanRun('db:wipe', 'testing', 'mysql', 'hfccf_backend');
    }
}
