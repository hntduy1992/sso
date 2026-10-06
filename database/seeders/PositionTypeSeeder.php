<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PositionType;
use Illuminate\Database\Seeder;

/**
 * Seeds the position_types lookup table with the standard position hierarchy.
 *
 * This seeder is idempotent (uses updateOrCreate) and safe to re-run.
 */
class PositionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            [
                'code' => PositionType::MEMBER,
                'name' => 'Tổ viên',
                'level' => 1,
                'applicable_to' => 'specialized_team',
            ],
            [
                'code' => PositionType::DEPUTY_TEAM_LEAD,
                'name' => 'Tổ phó',
                'level' => 2,
                'applicable_to' => 'specialized_team',
            ],
            [
                'code' => PositionType::TEAM_LEAD,
                'name' => 'Tổ trưởng',
                'level' => 3,
                'applicable_to' => 'specialized_team',
            ],
            [
                'code' => PositionType::DEPUTY_DIRECTOR,
                'name' => 'Phó Giám đốc',
                'level' => 4,
                'applicable_to' => 'management_board',
            ],
            [
                'code' => PositionType::DIRECTOR,
                'name' => 'Giám đốc',
                'level' => 5,
                'applicable_to' => 'management_board',
            ],
        ];

        foreach ($positions as $position) {
            PositionType::updateOrCreate(
                ['code' => $position['code']],
                array_merge($position, ['is_active' => true])
            );
        }

        $this->command?->info('Position types seeded: '.count($positions).' records.');
    }
}
