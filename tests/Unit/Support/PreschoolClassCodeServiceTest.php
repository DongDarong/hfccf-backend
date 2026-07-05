<?php

namespace Tests\Unit\Support;

use App\Models\PreschoolClassLevel;
use App\Support\PreschoolClassCodeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PreschoolClassCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_generate_next_code_uses_existing_sequence(): void
    {
        $service = app(PreschoolClassCodeService::class);
        $nurseryLevel = $this->classLevel('NUR');

        DB::table('preschool_classes')->insert([
            [
                'code' => 'PS-NUR-001',
                'name' => 'Existing Nursery One',
                'teacher_display_name' => null,
                'class_level_id' => $nurseryLevel->id,
                'level' => 'Nursery',
                'schedule' => null,
                'students_count' => 0,
                'status' => 'active',
                'room' => null,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'PS-NUR-002',
                'name' => 'Existing Nursery Two',
                'teacher_display_name' => null,
                'class_level_id' => $nurseryLevel->id,
                'level' => 'Nursery',
                'schedule' => null,
                'students_count' => 0,
                'status' => 'active',
                'room' => null,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame('PS-NUR-003', $service->generateNextCode($nurseryLevel));
    }

    public function test_create_with_retry_retries_duplicate_code_collision(): void
    {
        $service = new class extends PreschoolClassCodeService
        {
            private int $attempt = 0;

            public function generateNextCode(PreschoolClassLevel $classLevel): string
            {
                $this->attempt++;

                return $this->attempt === 1 ? 'PS-NUR-001' : 'PS-NUR-002';
            }
        };

        $result = $service->createWithRetry($this->classLevel('NUR'), function (string $code) {
            static $callCount = 0;
            $callCount++;

            if ($callCount === 1) {
                throw $this->duplicateCodeException($code);
            }

            return $code;
        }, 2);

        $this->assertSame('PS-NUR-002', $result);
    }

    public function test_create_with_retry_returns_safe_validation_error_after_retries_exhausted(): void
    {
        $service = new class extends PreschoolClassCodeService
        {
            public function generateNextCode(PreschoolClassLevel $classLevel): string
            {
                return 'PS-NUR-001';
            }
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unable to generate a unique class code. Please try again.');

        $service->createWithRetry($this->classLevel('NUR'), function (string $code) {
            throw $this->duplicateCodeException($code);
        }, 1);
    }

    private function classLevel(string $code): PreschoolClassLevel
    {
        return PreschoolClassLevel::query()->where('code', $code)->firstOrFail();
    }

    private function duplicateCodeException(string $code): QueryException
    {
        return new QueryException(
            'mysql',
            'insert into preschool_classes (code) values (?)',
            [$code],
            new \PDOException(
                sprintf(
                    "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '%s' for key 'preschool_classes_code_unique'",
                    $code,
                ),
                '23000',
            ),
        );
    }
}
