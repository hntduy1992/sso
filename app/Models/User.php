<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'status',
    'avatar_url',
    'password_changed_at',
    'two_factor_enabled',
    'two_factor_confirmed_at',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasMfaEnabled(): bool
    {
        return $this->two_factor_enabled && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Determine if an access token issued before the last password change
     * should be considered invalid — used by resource servers for token validation.
     */
    public function isTokenIssuedBeforePasswordChange(int $tokenIssuedAt): bool
    {
        if (! $this->password_changed_at) {
            return false;
        }

        return $tokenIssuedAt < $this->password_changed_at->timestamp;
    }

    /**
     * Get all linked social provider accounts.
     *
     * @return HasMany<UserSocialAccount>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    /**
     * Get active and historical sessions for this user.
     *
     * @return HasMany<UserSession>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Get audit logs triggered by this user.
     *
     * @return HasMany<AuditLog>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get refresh token rotation families owned by this user.
     *
     * @return HasMany<OAuthRefreshTokenFamily>
     */
    public function refreshTokenFamilies(): HasMany
    {
        return $this->hasMany(OAuthRefreshTokenFamily::class);
    }
}
