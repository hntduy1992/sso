<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Models an organizational position assignment — the core HRM pivot.
 *
 * Supports concurrent roles (kiêm nhiệm):
 *   A Phó Giám đốc (is_primary=true, in Ban Giám đốc) may also hold
 *   Tổ trưởng positions (is_primary=false) in one or more specialized teams.
 *
 * Business Rules enforced at the Application layer (Use Cases):
 *   BR-03: Only ONE active row per user may have is_primary=true.
 *   BR-04: Each specialized team may have only ONE active TEAM_LEAD.
 *   BR-05: ended_at IS NULL → position is currently active.
 *
 * @property int $id
 * @property int $user_id
 * @property int $department_id
 * @property int $position_type_id
 * @property string $started_at (Y-m-d)
 * @property string|null $ended_at (Y-m-d, NULL = active)
 * @property bool $is_primary
 * @property string|null $notes
 */
#[Fillable([
    'user_id',
    'department_id',
    'position_type_id',
    'started_at',
    'ended_at',
    'is_primary',
    'notes',
])]
class UserPosition extends Model
{
    /**
     * Get the account holder of this position.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the department for this position.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the position type (chức danh) for this record.
     *
     * @return BelongsTo<PositionType, $this>
     */
    public function positionType(): BelongsTo
    {
        return $this->belongsTo(PositionType::class);
    }

    // -----------------------------------------------------------------------
    // Query Scopes
    // -----------------------------------------------------------------------

    /**
     * Scope to only currently active positions (ended_at IS NULL).
     *
     * @param  Builder<UserPosition>  $query
     * @return Builder<UserPosition>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope to only primary (non-concurrent) positions.
     *
     * @param  Builder<UserPosition>  $query
     * @return Builder<UserPosition>
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to only concurrent (kiêm nhiệm) positions.
     *
     * @param  Builder<UserPosition>  $query
     * @return Builder<UserPosition>
     */
    public function scopeConcurrent(Builder $query): Builder
    {
        return $query->where('is_primary', false);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function isConcurrent(): bool
    {
        return ! $this->is_primary;
    }

    /**
     * Terminate this position by setting ended_at to today.
     */
    public function terminate(?string $notes = null): bool
    {
        return $this->update([
            'ended_at' => now()->toDateString(),
            'notes' => $notes ?? $this->notes,
        ]);
    }
}
