<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RefreshToken extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'revoked_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Get the user that owns the refresh token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new refresh token.
     */
    public static function generateFor(User $user): self
    {
        // Revoke any existing tokens for this user
        static::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $expirationMinutes = config('sanctum.refresh_expiration', 20160);

        return static::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes($expirationMinutes),
        ]);
    }

    /**
     * Check if the token is valid.
     */
    public function isValid(): bool
    {
        return is_null($this->revoked_at) && $this->expires_at->isFuture();
    }

    /**
     * Revoke this token.
     */
    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    /**
     * Find a valid token by its value.
     */
    public static function findValidToken(string $token): ?self
    {
        return static::where('token', $token)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Scope: Only valid tokens.
     */
    public function scopeValid($query)
    {
        return $query->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: Only expired tokens.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}
