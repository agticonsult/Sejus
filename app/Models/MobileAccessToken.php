<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use DateTimeInterface;

class MobileAccessToken extends Model
{
    use HasFactory;

    protected $table = 'mobile_access_tokens';

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * User associated with this mobile access token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if the token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if the token is valid (not expired).
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Generate and store a new secure mobile token for a given user.
     *
     * @param User $user
     * @param string $name
     * @param DateTimeInterface|null $expiresAt
     * @param array $abilities
     * @return array{accessToken: MobileAccessToken, plainTextToken: string}
     */
    public static function createTokenForUser(
        User $user,
        string $name = 'mobile',
        ?DateTimeInterface $expiresAt = null,
        array $abilities = ['*']
    ): array {
        $plainTextToken = 'ce_mob_' . bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainTextToken);
        $expires = $expiresAt ?? now()->addDays(30);

        $model = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => $hashedToken,
            'abilities' => $abilities,
            'expires_at' => $expires,
        ]);

        return [
            'accessToken' => $model,
            'plainTextToken' => $plainTextToken,
        ];
    }
}
