<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PersonalAccessToken extends Model
{
    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    /**
     * @return array{0:self,1:string}
     */
    public static function issueFor(User $user, string $name = 'auth-token'): array
    {
        $plainTextToken = Str::random(64);

        $token = static::query()->create([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->getKey(),
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
        ]);

        return [$token, $token->id.'|'.$plainTextToken];
    }

    public static function findToken(string $token): ?self
    {
        if (! str_contains($token, '|')) {
            return null;
        }

        [$id, $plainTextToken] = explode('|', $token, 2);

        if ($id === '' || $plainTextToken === '') {
            return null;
        }

        /** @var self|null $accessToken */
        $accessToken = static::query()->find($id);

        if (! $accessToken) {
            return null;
        }

        return hash_equals($accessToken->token, hash('sha256', $plainTextToken))
            ? $accessToken
            : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tokenable_id', 'id');
    }
}
