<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '628'.fake()->unique()->numerify('##########'),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /** Super Admin platform (tanpa tenant). */
    public function superAdmin(): static
    {
        return $this->state(fn () => ['tenant_id' => null])
            ->afterMaking(fn ($user) => $user->is_super_admin = true);
    }
}
