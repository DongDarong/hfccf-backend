<?php

namespace App\Support;

use App\Models\PreschoolClass;
use App\Models\PreschoolClassLevel;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreschoolClassCodeService
{
    public function createWithRetry(PreschoolClassLevel $classLevel, Closure $creator, int $maxAttempts = 5)
    {
        $attempts = max(1, $maxAttempts);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return DB::transaction(function () use ($classLevel, $creator) {
                    $code = $this->generateNextCode($classLevel);

                    return $creator($code);
                });
            } catch (QueryException $exception) {
                if (! $this->isDuplicateCodeException($exception)) {
                    throw $exception;
                }

                if ($attempt === $attempts) {
                    throw ValidationException::withMessages([
                        'code' => ['Unable to generate a unique class code. Please try again.'],
                    ]);
                }
            }
        }

        throw ValidationException::withMessages([
            'code' => ['Unable to generate a unique class code. Please try again.'],
        ]);
    }

    public function generateNextCode(PreschoolClassLevel $classLevel): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $classLevel->code)) ?: 'CLS';
        $existingCodes = PreschoolClass::query()
            ->where('code', 'like', 'PS-'.$prefix.'-%')
            ->lockForUpdate()
            ->pluck('code');

        $sequence = $existingCodes
            ->reduce(static function (int $max, string $code) use ($prefix): int {
                $pattern = sprintf('/^PS-%s-(\d+)$/', preg_quote($prefix, '/'));
                if (! preg_match($pattern, strtoupper($code), $matches)) {
                    return $max;
                }

                return max($max, (int) $matches[1]);
            }, 0) + 1;

        return sprintf('PS-%s-%03d', $prefix, $sequence);
    }

    private function isDuplicateCodeException(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return $sqlState === '23000'
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'duplicate key')
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'unique constraint');
    }
}
