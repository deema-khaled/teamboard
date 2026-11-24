<?php

use App\Events\BoardChanged;
use App\Models\Board;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->member = User::factory()->create();
    $this->board = Board::factory()->withMembers($this->member)->create();
    [$this->todo, $this->doing] = $this->board->columns()->get()->all();
});

/** Card titles of a column, top to bottom. */
function titles($column): array
{
    return $column->cards()->pluck('title')->all();
}

test('new cards go to the bottom of the column', function () {
    foreach (['A', 'B', 'C'] as $title) {
        $this->actingAs($this->member)->postJson("/api/v1/columns/{$this->todo->id}/cards", ['title' => $title])->assertCreated();
    }

    expect(titles($this->todo))->toBe(['A', 'B', 'C'])
        ->and($this->todo->cards()->pluck('position')->all())->toBe([0, 1, 2]);
});

test('a card can be reordered within its column', function () {
    $cards = collect(['A', 'B', 'C', 'D'])->map(fn ($t) => Card::factory()->in($this->todo)->create(['title' => $t]));

    $this->actingAs($this->member)
        ->patchJson("/api/v1/cards/{$cards[3]->id}/move", ['column_id' => $this->todo->id, 'position' => 1])
        ->assertOk();

    expect(titles($this->todo))->toBe(['A', 'D', 'B', 'C'])
        ->and($this->todo->cards()->pluck('position')->all())->toBe([0, 1, 2, 3]);
});

test('moving a card to another column re-sequences both columns', function () {
    $a = Card::factory()->in($this->todo)->create(['title' => 'A']);
    Card::factory()->in($this->todo)->create(['title' => 'B']);
    Card::factory()->in($this->doing)->create(['title' => 'X']);

    $response = $this->actingAs($this->member)
        ->patchJson("/api/v1/cards/{$a->id}/move", ['column_id' => $this->doing->id, 'position' => 1])
        ->assertOk();

    expect(titles($this->todo))->toBe(['B'])
        ->and(titles($this->doing))->toBe(['X', 'A'])
        ->and($this->todo->cards()->value('position'))->toBe(0)
        ->and($response->json("data.orders.{$this->doing->id}"))->toBe([$this->doing->cards()->first()->id, $a->id]);
});

test('positions past the end are clamped', function () {
    $a = Card::factory()->in($this->todo)->create(['title' => 'A']);
    Card::factory()->in($this->todo)->create(['title' => 'B']);

    $this->actingAs($this->member)->patchJson("/api/v1/cards/{$a->id}/move", ['column_id' => $this->todo->id, 'position' => 99]);

    expect(titles($this->todo))->toBe(['B', 'A']);
});

test('a card cannot be moved into another board\'s column', function () {
    $card = Card::factory()->in($this->todo)->create();
    $foreignColumn = Board::factory()->create()->columns()->first();

    $this->actingAs($this->member)
        ->patchJson("/api/v1/cards/{$card->id}/move", ['column_id' => $foreignColumn->id, 'position' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('column_id');
});

test('only board members can be assigned', function () {
    $outsider = User::factory()->create();

    $this->actingAs($this->member)
        ->postJson("/api/v1/columns/{$this->todo->id}/cards", ['title' => 'Task', 'assignee_id' => $outsider->id])
        ->assertJsonValidationErrors('assignee_id');

    $this->actingAs($this->member)
        ->postJson("/api/v1/columns/{$this->todo->id}/cards", ['title' => 'Task', 'assignee_id' => $this->board->owner_id, 'due_date' => '2026-12-01', 'label' => 'red'])
        ->assertCreated()
        ->assertJsonPath('data.assignee.id', $this->board->owner_id)
        ->assertJsonPath('data.due_date', '2026-12-01');
});

test('outsiders cannot touch cards', function () {
    $card = Card::factory()->in($this->todo)->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->getJson("/api/v1/cards/{$card->id}")->assertForbidden();
    $this->actingAs($outsider)->patchJson("/api/v1/cards/{$card->id}", ['title' => 'x'])->assertForbidden();
    $this->actingAs($outsider)->postJson("/api/v1/columns/{$this->todo->id}/cards", ['title' => 'x'])->assertForbidden();
    $this->actingAs($outsider)->deleteJson("/api/v1/cards/{$card->id}")->assertForbidden();
});

test('deleting a card closes the gap', function () {
    Card::factory()->in($this->todo)->create(['title' => 'A']);
    $b = Card::factory()->in($this->todo)->create(['title' => 'B']);
    Card::factory()->in($this->todo)->create(['title' => 'C']);

    $this->actingAs($this->member)->deleteJson("/api/v1/cards/{$b->id}")->assertNoContent();

    expect($this->todo->cards()->pluck('position')->all())->toBe([0, 1]);
});

test('changes are broadcast to the board\'s presence channel', function () {
    Event::fake([BoardChanged::class]);
    $card = Card::factory()->in($this->todo)->create();

    $this->actingAs($this->member)->patchJson("/api/v1/cards/{$card->id}/move", ['column_id' => $this->doing->id, 'position' => 0]);

    Event::assertDispatched(BoardChanged::class, function (BoardChanged $event) use ($card) {
        return $event->type === 'card.moved'
            && $event->broadcastOn()->name === "presence-board.{$this->board->id}"
            && $event->broadcastWith()['data']['card']['id'] === $card->id
            && $event->broadcastWith()['actor']['id'] === $this->member->id;
    });
});

test('comments can be added and only deleted by their author', function () {
    $card = Card::factory()->in($this->todo)->create();

    $id = $this->actingAs($this->member)->postJson("/api/v1/cards/{$card->id}/comments", ['body' => 'Looks good'])
        ->assertCreated()->assertJsonPath('data.user.id', $this->member->id)->json('data.id');

    $this->actingAs($this->board->owner)->deleteJson("/api/v1/comments/{$id}")->assertForbidden();
    $this->actingAs($this->member)->deleteJson("/api/v1/comments/{$id}")->assertNoContent();

    $this->actingAs($this->member)->getJson("/api/v1/cards/{$card->id}")->assertJsonCount(0, 'data.comments');
});
