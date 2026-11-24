<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Board>
 */
class BoardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'description' => fake()->sentence(),
            'color' => fake()->randomElement(Board::COLORS),
        ];
    }

    /** Adds users as plain members. */
    public function withMembers(User ...$members): static
    {
        return $this->afterCreating(fn (Board $board) => $board->members()->attach(
            collect($members)->mapWithKeys(fn (User $u) => [$u->id => ['role' => 'member']])->all()
        ));
    }
}
