<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An organizational unit within the institution.
 *
 * type = 'management_board' → Ban Giám đốc
 * type = 'specialized_team' → Tổ chuyên môn
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $type (management_board|specialized_team)
 * @property string|null $description
 * @property bool $is_active
 * @property int $display_order
 */
#[Fillable([
    'name',
    'code',
    'type',
    'description',
    'is_active',
    'display_order',
])]
class Department extends Model
{
    use SoftDeletes;

    /**
     * Get all position assignments for this department.
     *
     * @return HasMany<UserPosition, $this>
     */
    public function userPositions(): HasMany
    {
        return $this->hasMany(UserPosition::class);
    }

    /**
     * Get currently active position assignments.
     *
     * @return HasMany<UserPosition, $this>
     */
    public function activePositions(): HasMany
    {
        return $this->hasMany(UserPosition::class)->whereNull('ended_at');
    }

    public function isManagementBoard(): bool
    {
        return $this->type === 'management_board';
    }

    public function isSpecializedTeam(): bool
    {
        return $this->type === 'specialized_team';
    }

    /**
     * Get all users currently holding an active position in this department.
     *
     * @return Collection<int, User>
     */
    public function activeMembers(): Collection
    {
        return User::whereHas('positions', function ($query) {
            $query->where('department_id', $this->id)->whereNull('ended_at');
        })->get();
    }
}
