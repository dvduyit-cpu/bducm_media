<?php

namespace Database\Factories;

use App\Models\AccessRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccessRole> */
class AccessRoleFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => 'custom_'.fake()->unique()->numerify('########'), 'name' => fake()->jobTitle(), 'base_role' => 'staff', 'permissions' => ['dashboard', 'requests', 'calendar']];
    }
}
