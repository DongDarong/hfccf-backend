<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Controllers\Controller;
use App\Models\SportMatch;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

abstract class SportController extends Controller
{
    protected function authorizeSportAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminsport'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }

    protected function authorizeSportCoach(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminsport', 'coach'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }

    protected function isSportAdmin(?User $user): bool
    {
        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function isSportCoach(?User $user): bool
    {
        return (bool) $user && $user->role_code === 'coach';
    }

    protected function resolveUserReference(?string $value, ?string $roleCode = null): ?User
    {
        $reference = trim((string) $value);

        if ($reference === '') {
            return null;
        }

        $referenceLower = mb_strtolower($reference);
        [$firstName, $lastName] = $this->splitNameReference($referenceLower);

        return User::query()
            ->when($roleCode, static function ($query) use ($roleCode): void {
                $query->where('role_code', $roleCode);
            })
            ->where(function ($query) use ($reference): void {
                $referenceLower = mb_strtolower($reference);
                [$firstName, $lastName] = $this->splitNameReference($referenceLower);

                $query->where('id', $reference)
                    ->orWhere('email', $reference)
                    ->orWhere('username', $reference)
                    ->orWhere(function ($nameQuery) use ($firstName, $lastName): void {
                        $nameQuery->whereRaw('LOWER(first_name) = ?', [$firstName])
                            ->whereRaw('LOWER(last_name) = ?', [$lastName]);
                    })
                    ->orWhereRaw('LOWER(first_name) = ?', [mb_strtolower($reference)])
                    ->orWhereRaw('LOWER(last_name) = ?', [mb_strtolower($reference)]);
            })
            ->first();
    }

    protected function resolveTeamReference(?string $value): ?SportTeam
    {
        $reference = trim((string) $value);

        if ($reference === '') {
            return null;
        }

        return SportTeam::query()
            ->where(function ($query) use ($reference): void {
                $query->where('id', $reference)
                    ->orWhere('team_code', $reference)
                    ->orWhere('name', $reference)
                    ->orWhere('short_name', $reference);
            })
            ->first();
    }

    protected function resolvePlayerReference(?string $value): ?SportPlayer
    {
        $reference = trim((string) $value);

        if ($reference === '') {
            return null;
        }

        $referenceLower = mb_strtolower($reference);
        [$firstName, $lastName] = $this->splitNameReference($referenceLower);

        return SportPlayer::query()
            ->where(function ($query) use ($reference): void {
                $referenceLower = mb_strtolower($reference);
                [$firstName, $lastName] = $this->splitNameReference($referenceLower);

                $query->where('id', $reference)
                    ->orWhere('player_code', $reference)
                    ->orWhere(function ($nameQuery) use ($firstName, $lastName): void {
                        $nameQuery->whereRaw('LOWER(first_name) = ?', [$firstName])
                            ->whereRaw('LOWER(last_name) = ?', [$lastName]);
                    })
                    ->orWhereRaw('LOWER(first_name) = ?', [mb_strtolower($reference)])
                    ->orWhereRaw('LOWER(last_name) = ?', [mb_strtolower($reference)]);
            })
            ->first();
    }

    protected function storeSportFile(?UploadedFile $file, string $directory): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store($directory, 'public');
    }

    protected function deleteSportFile(?string $path): void
    {
        $storedPath = trim((string) $path);

        if ($storedPath === '') {
            return;
        }

        if (preg_match('/^https?:\/\//i', $storedPath) === 1) {
            return;
        }

        if (str_starts_with($storedPath, asset('storage/'))) {
            $storedPath = str_replace(asset('storage/'), '', $storedPath);
        }

        $storedPath = ltrim($storedPath, '/');

        Storage::disk('public')->delete($storedPath);
    }

    protected function makeSportCode(string $prefix): string
    {
        return strtoupper($prefix.'-'.Str::random(8));
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitNameReference(string $reference): array
    {
        $reference = trim(preg_replace('/\s+/', ' ', $reference) ?? $reference);

        if ($reference === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $reference, 2) ?: [$reference, ''];

        return [
            trim((string) ($parts[0] ?? '')),
            trim((string) ($parts[1] ?? '')),
        ];
    }
}
