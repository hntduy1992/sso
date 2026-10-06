<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApplicationAccessGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permission for a single user, or every active member of a department,
 * to sign in to an OAuth application (satellite app).
 *
 * Exactly one of user_id / department_id is set.
 *
 * @property int $id
 * @property string $client_id
 * @property int|null $user_id
 * @property int|null $department_id
 * @property int|null $granted_by
 */
#[Fillable([
    'client_id',
    'user_id',
    'department_id',
    'granted_by',
])]
class ApplicationAccessGrant extends Model
{
    /** @use HasFactory<ApplicationAccessGrantFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
