<?php

namespace Database\Factories;

use App\Models\Person;
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
            'person_id'   => null,
            'external_id' => Str::uuid()->toString(),
            'username'    => fake()->unique()->userName(),
            'email'       => fake()->unique()->safeEmail(),
            'name'        => "{$firstName} {$lastName}",
            'fname'       => $firstName,
            'lname'       => $lastName,
        ];
    }

    public function withPerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'person_id' => Person::factory()->create(['email' => $attributes['email'], 'name' => $attributes['name']])->id,
        ]);
    }
}
