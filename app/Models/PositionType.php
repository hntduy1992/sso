<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lookup table for position types (chức danh).
 *
 * The `applicable_to` column enforces which department type may use
 * each position, preventing invalid assignments such as assigning
 * "Giám đốc" to a specialized_team.
 *
 * @property int $id
 * @property string $code (MEMBER|DEPUTY_TL|TEAM_LEAD|DEPUTY_DIR|DIRECTOR)
 * @property string $name (Tổ viên|Tổ phó|Tổ trưởng|Phó Giám đốc|Giám đốc)
 * @property int $level (1=lowest authority, 5=highest)
 * @property string $applicable_to (specialized_team|management_board|both)
 * @property bool $is_active
 */
#[Fillable([
    'code',
    'name',
    'level',
    'applicable_to',
    'is_active',
])]
class PositionType extends Model
{
    // Position codes as constants for safe reference throughout the codebase
    public const string MEMBER = 'MEMBER';

    public const string DEPUTY_TEAM_LEAD = 'DEPUTY_TL';

    public const string TEAM_LEAD = 'TEAM_LEAD';

    public const string DEPUTY_DIRECTOR = 'DEPUTY_DIR';

    public const string DIRECTOR = 'DIRECTOR';

    /**
     * Get all user position assignments using this position type.
     *
     * @return HasMany<UserPosition, $this>
     */
    public function userPositions(): HasMany
    {
        return $this->hasMany(UserPosition::class);
    }

    public function isTeamLead(): bool
    {
        return $this->code === self::TEAM_LEAD;
    }

    public function isDirector(): bool
    {
        return $this->code === self::DIRECTOR;
    }

    public function isDeputyDirector(): bool
    {
        return $this->code === self::DEPUTY_DIRECTOR;
    }

    public function isApplicableTo(string $departmentType): bool
    {
        return $this->applicable_to === 'both' || $this->applicable_to === $departmentType;
    }
}
