<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName  = fake()->lastName();

        return [
            'external_id' => Str::uuid()->toString(),
            'username'    => fake()->unique()->userName(),
            'email'       => fake()->unique()->safeEmail(),
            'name'        => "{$firstName} {$lastName}",
            'fname'       => $firstName,
            'lname'       => $lastName,
        ];
    }
}
