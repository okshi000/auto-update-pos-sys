<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Attempt to authenticate a user and generate tokens.
     *
     * @return array{user: User, access_token: string, refresh_token: string, expires_in: int}|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            $this->auditService->logFailedLogin($email);
            return null;
        }

        if (!$user->is_active) {
            return null;
        }

        return $this->createAuthTokens($user);
    }

    /**
     * Create auth tokens for a user.
     *
     * @return array{user: User, access_token: string, refresh_token: string, expires_in: int}
     */
    public function createAuthTokens(User $user): array
    {
        // Delete old access tokens
        $user->tokens()->delete();

        // Create new access token
        $expirationMinutes = config('sanctum.expiration', 60);
        $accessToken = $user->createToken('access_token');

        // Create refresh token
        $refreshToken = RefreshToken::generateFor($user);

        // Log login
        $this->auditService->logLogin($user);

        return [
            'user' => $user->load('roles'),
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->token,
            'expires_in' => $expirationMinutes * 60, // in seconds
        ];
    }

    /**
     * Refresh tokens using a refresh token.
     *
     * @return array{user: User, access_token: string, refresh_token: string, expires_in: int}|null
     */
    public function refreshTokens(string $refreshTokenValue): ?array
    {
        $refreshToken = RefreshToken::findValidToken($refreshTokenValue);

        if (!$refreshToken) {
            return null;
        }

        $user = $refreshToken->user;

        if (!$user || !$user->is_active) {
            return null;
        }

        // Revoke old refresh token
        $refreshToken->revoke();

        // Delete old access tokens
        $user->tokens()->delete();

        // Create new tokens
        $expirationMinutes = config('sanctum.expiration', 60);
        $newAccessToken = $user->createToken('access_token');
        $newRefreshToken = RefreshToken::generateFor($user);

        return [
            'user' => $user->load('roles'),
            'access_token' => $newAccessToken->plainTextToken,
            'refresh_token' => $newRefreshToken->token,
            'expires_in' => $expirationMinutes * 60,
        ];
    }

    /**
     * Logout a user by revoking all tokens.
     */
    public function logout(User $user): void
    {
        // Revoke all access tokens
        $user->tokens()->delete();

        // Revoke all refresh tokens
        RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        // Log logout
        $this->auditService->logLogout($user);
    }

    /**
     * Get user by ID with roles loaded.
     */
    public function getUserWithRoles(int $userId): ?User
    {
        return User::with('roles.permissions')->find($userId);
    }
}
