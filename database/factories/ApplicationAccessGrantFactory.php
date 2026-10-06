<?php

namespace Database\Factories;

use App\Models\ApplicationAccessGrant;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationAccessGrant>
 */
class ApplicationAccessGrantFactory extends Factory
{
    /**
     * Define the model's default state (a per-user grant; client_id must be supplied).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'department_id' => null,
        ];
    }

    public function forDepartment(?Department $department = null): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'department_id' => $department?->id ?? Department::query()->firstOrFail()->id,
        ]);
    }
}
