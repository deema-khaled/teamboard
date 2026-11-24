<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\Card;
use App\Models\Column;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->words(fake()->numberBetween(2, 5), true)),
            'description' => fake()->optional()->paragraph(),
            'label' => fake()->optional()->randomElement(Card::LABELS),
            'position' => 0,
        ];
    }

    /** Puts the card at the bottom of a column (board and creator follow the column). */
    public function in(Column $column): static
    {
        return $this->state(fn () => [
            'board_id' => $column->board_id,
            'column_id' => $column->id,
            'created_by' => Board::whereKey($column->board_id)->value('owner_id'),
            'position' => Card::where('column_id', $column->id)->count(),
        ]);
    }
}
