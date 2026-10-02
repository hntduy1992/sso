<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Passport;

class UserSession extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_sessions';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'client_id',
        'ip_address',
        'user_agent',
        'last_activity',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'bool',
        'last_activity' => 'datetime',
    ];

    /**
     * The user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The client application associated with this session.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Passport::clientModel(), 'client_id');
    }
}
