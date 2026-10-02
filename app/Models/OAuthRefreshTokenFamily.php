<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Passport\Passport;

class OAuthRefreshTokenFamily extends Model
{
    use HasUuids;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'oauth_refresh_token_families';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'root_access_token_id',
        'current_refresh_token_id',
        'user_id',
        'client_id',
        'revoked',
        'revoked_at',
        'revoked_reason',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'revoked' => 'bool',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * The user that owns the token family.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The client application this family was issued for.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Passport::clientModel(), 'client_id');
    }

    /**
     * All refresh tokens issued within this rotation chain.
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(OAuthRefreshToken::class, 'family_id');
    }

    /**
     * Revoke the entire family and all associated access and refresh tokens.
     */
    public function revokeFamily(string $reason = 'manual_revocation'): void
    {
        $this->update([
            'revoked' => true,
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ]);

        // Revoke all refresh tokens in this family
        $refreshTokens = $this->refreshTokens()->get();
        $this->refreshTokens()->update(['revoked' => true]);

        // Revoke all corresponding access tokens
        $accessTokenIds = $refreshTokens->pluck('access_token_id')->filter()->unique();
        if ($accessTokenIds->isNotEmpty()) {
            Passport::token()->whereIn('id', $accessTokenIds)->update(['revoked' => true]);
        }
    }

    /**
     * Get the current connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return parent::getConnectionName() ?? config('passport.connection');
    }
}
