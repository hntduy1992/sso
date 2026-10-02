<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\RefreshToken as PassportRefreshToken;

/**
 * Custom Passport RefreshToken model supporting Revocation Family.
 */
class OAuthRefreshToken extends PassportRefreshToken
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'access_token_id',
        'family_id',
        'revoked',
        'expires_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'revoked' => 'bool',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the token family that this refresh token belongs to.
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(OAuthRefreshTokenFamily::class, 'family_id');
    }
}
