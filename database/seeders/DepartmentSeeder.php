<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Seeds the departments table with the management board and sample
 * specialized teams.
 *
 * Modify the $specializedTeams array below to reflect the actual
 * teams of your organization before running in production.
 *
 * This seeder is idempotent (uses updateOrCreate) and safe to re-run.
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ban Giám đốc — always a single management_board department
        Department::updateOrCreate(
            ['code' => 'BGD'],
            [
                'name' => 'Ban Giám đốc',
                'type' => 'management_board',
                'description' => 'Ban lãnh đạo đơn vị gồm Giám đốc và các Phó Giám đốc.',
                'is_active' => true,
                'display_order' => 0,
            ]
        );

        // 2. Specialized Teams — update this list to match actual structure
        $specializedTeams = [
            [
                'code' => 'TCM-01',
                'name' => 'Tổ Chuyên môn 1',
                'description' => 'Tổ chuyên môn số 1.',
                'display_order' => 1,
            ],
            [
                'code' => 'TCM-02',
                'name' => 'Tổ Chuyên môn 2',
                'description' => 'Tổ chuyên môn số 2.',
                'display_order' => 2,
            ],
        ];

        foreach ($specializedTeams as $team) {
            Department::updateOrCreate(
                ['code' => $team['code']],
                array_merge($team, [
                    'type' => 'specialized_team',
                    'is_active' => true,
                ])
            );
        }

        $total = 1 + count($specializedTeams);
        $this->command?->info("Departments seeded: {$total} records (1 management board + ".count($specializedTeams).' specialized teams).');
    }
}
