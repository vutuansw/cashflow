<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'full_name' => fake()->name(),
            'phone' => '097'.fake()->unique()->numerify('#######'),
        ];
    }
}
