<?php

namespace Tests\Unit;

use Tests\TestCase;

class EnvironmentSafetyTest extends TestCase
{
    public function test_env_testing_exists_and_points_to_isolated_database(): void
    {
        $path = base_path('.env.testing');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertIsString($content);
        $this->assertStringContainsString("APP_ENV=testing", $content);
        $this->assertStringContainsString("DB_CONNECTION=mysql", $content);
        $this->assertStringContainsString("DB_DATABASE=hfccf_backend_testing", $content);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]+$/m', $content);
        $this->assertStringContainsString("SESSION_DRIVER=array", $content);
        $this->assertStringContainsString("QUEUE_CONNECTION=sync", $content);
        $this->assertStringContainsString("CACHE_STORE=array", $content);
        $this->assertStringContainsString("MAIL_MAILER=array", $content);
    }

    public function test_phpunit_uses_an_isolated_testing_database_configuration(): void
    {
        $content = file_get_contents(base_path('phpunit.xml'));

        $this->assertIsString($content);
        $this->assertStringContainsString('name="APP_ENV" value="testing"', $content);
        $this->assertStringContainsString('name="APP_KEY" value="base64:QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUE="', $content);
        $this->assertStringContainsString('name="DB_CONNECTION" value="sqlite"', $content);
        $this->assertStringContainsString('name="DB_DATABASE" value=":memory:"', $content);
        $this->assertStringContainsString('name="QUEUE_CONNECTION" value="sync"', $content);
        $this->assertStringContainsString('name="SESSION_DRIVER" value="array"', $content);
        $this->assertStringContainsString('name="MAIL_MAILER" value="array"', $content);
    }
}
