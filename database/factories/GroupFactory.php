<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'nhom-'.fake()->unique()->numerify('####'),
            'name' => 'Nhóm '.fake()->words(2, true),
            'visibility' => 'public',
            'owner_id' => User::factory(),
            'opening_balance' => 0,
        ];
    }

    public function private(): static
    {
        return $this->state(['visibility' => 'private']);
    }
}
