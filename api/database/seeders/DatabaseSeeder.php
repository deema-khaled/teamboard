<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo team: every password is "password". Log in as sara@teamboard.test
 * in one browser and omar@teamboard.test in another to see real-time updates.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $sara = User::factory()->create(['name' => 'Sara Haddad', 'email' => 'sara@teamboard.test']);
        $omar = User::factory()->create(['name' => 'Omar Khalil', 'email' => 'omar@teamboard.test']);
        $lina = User::factory()->create(['name' => 'Lina Nasser', 'email' => 'lina@teamboard.test']);

        $this->board($sara, [$omar, $lina], 'Website relaunch', 'New marketing site, launching next month.', 'indigo', [
            'To do' => [
                ['Write copy for the pricing page', 'yellow', $lina, 3],
                ['Collect customer testimonials', null, null, 6],
                ['Set up analytics events', 'blue', $omar, 9],
            ],
            'In progress' => [
                ['Design the new homepage hero', 'purple', $sara, 1],
                ['Migrate blog posts', null, $omar, 4],
            ],
            'Review' => [
                ['Mobile navigation', 'green', $lina, -1],
            ],
            'Done' => [
                ['Pick the brand colours', 'purple', $sara, null],
                ['Choose hosting provider', null, $omar, null],
            ],
        ]);

        $this->board($omar, [$sara], 'Mobile app v2', 'Offline mode and push notifications.', 'emerald', [
            'To do' => [['Offline storage spike', 'red', $omar, 2], ['Push notification copy', null, $sara, 5]],
            'In progress' => [['Login with Apple', 'blue', $omar, 1]],
            'Review' => [],
            'Done' => [['Crash reporting', 'green', $sara, null]],
        ]);

        $this->board($sara, [], 'Personal', 'My own to-dos.', 'amber', [
            'To do' => [['Prepare portfolio review', null, $sara, 7]],
            'In progress' => [],
            'Review' => [],
            'Done' => [],
        ]);
    }

    /** @param array<string, list<array{0: string, 1: ?string, 2: ?User, 3: ?int}>> $cards */
    private function board(User $owner, array $members, string $name, string $description, string $color, array $cards): void
    {
        $board = new Board(compact('name', 'description', 'color'));
        $board->owner_id = $owner->id;
        $board->save();

        foreach ($members as $member) {
            $board->members()->attach($member->id, ['role' => 'member']);
        }

        $columns = $board->columns()->get()->keyBy('name');

        foreach ($cards as $columnName => $items) {
            foreach ($items as $position => [$title, $label, $assignee, $dueInDays]) {
                $card = new Card([
                    'title' => $title,
                    'label' => $label,
                    'assignee_id' => $assignee?->id,
                    'due_date' => $dueInDays === null ? null : today()->addDays($dueInDays),
                    'position' => $position,
                ]);
                $card->board_id = $board->id;
                $card->column_id = $columns[$columnName]->id;
                $card->created_by = $owner->id;
                $card->save();

                if ($position === 0 && $members) {
                    $card->comments()->create(['user_id' => $members[0]->id, 'body' => 'I can pick this up after lunch 👍']);
                }
            }
        }

        $board->log($owner, 'board.created', 'created the board');
    }
}
