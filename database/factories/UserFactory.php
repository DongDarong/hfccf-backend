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
    protected static string $password = 'password';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'id' => 'usr_'.fake()->unique()->numerify('###'),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $firstName.' '.$lastName,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'role_code' => 'superadmin',
            'department_code' => 'operations',
            'status' => 'active',
            'avatar' => fake()->optional()->imageUrl(200, 200),
            'email_verified_at' => now(),
            'password' => static::$password,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function asAdminEnglish(): static
    {
        return $this->state(['role_code' => 'adminenglish', 'department_code' => 'education']);
    }

    public function asAdminPreschool(): static
    {
        return $this->state(['role_code' => 'adminpreschool', 'department_code' => 'education']);
    }

    public function asAdminScholarship(): static
    {
        return $this->state(['role_code' => 'adminscholarship', 'department_code' => 'education']);
    }

    public function asAdminSport(): static
    {
        return $this->state(['role_code' => 'adminsport', 'department_code' => 'sports']);
    }

    public function asCoach(): static
    {
        return $this->state(['role_code' => 'coach', 'department_code' => 'sports']);
    }

    public function asTeacherEnglish(): static
    {
        return $this->state(['role_code' => 'teacher-english', 'department_code' => 'education']);
    }

    public function asTeacherPreschool(): static
    {
        return $this->state(['role_code' => 'teacher-preschool', 'department_code' => 'education']);
    }

    public function asTeacherScholarship(): static
    {
        return $this->state(['role_code' => 'teacher-scholarship', 'department_code' => 'education']);
    }
}
