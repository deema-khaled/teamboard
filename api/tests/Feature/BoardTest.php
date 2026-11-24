<?php

use App\Models\Board;
use App\Models\User;

test('creating a board makes you the owner and adds default columns', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/boards', ['name' => 'Launch', 'color' => 'emerald'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Launch')
        ->assertJsonPath('data.is_owner', true)
        ->assertJsonPath('data.members.0.role', 'owner')
        ->assertJsonCount(4, 'data.columns')
        ->assertJsonPath('data.columns.0.name', 'To do');
});

test('users only see boards they belong to', function () {
    $me = User::factory()->create();
    Board::factory()->for($me, 'owner')->create();
    Board::factory()->withMembers($me)->create();
    Board::factory()->create(); // someone else's

    $this->actingAs($me)->getJson('/api/v1/boards')->assertOk()->assertJsonCount(2, 'data');
});

test('non-members cannot open a board', function () {
    $board = Board::factory()->create();

    $this->actingAs(User::factory()->create())->getJson("/api/v1/boards/{$board->id}")->assertForbidden();
});

test('members can view but only the owner can rename or delete', function () {
    $member = User::factory()->create();
    $board = Board::factory()->withMembers($member)->create();

    $this->actingAs($member)->getJson("/api/v1/boards/{$board->id}")->assertOk()->assertJsonPath('data.is_owner', false);
    $this->actingAs($member)->patchJson("/api/v1/boards/{$board->id}", ['name' => 'Hijacked'])->assertForbidden();
    $this->actingAs($member)->deleteJson("/api/v1/boards/{$board->id}")->assertForbidden();

    $this->actingAs($board->owner)->patchJson("/api/v1/boards/{$board->id}", ['name' => 'Renamed'])->assertOk();
    $this->actingAs($board->owner)->deleteJson("/api/v1/boards/{$board->id}")->assertNoContent();
    expect(Board::count())->toBe(0);
});

test('columns can be added, renamed, reordered and deleted', function () {
    $board = Board::factory()->create();
    $owner = $board->owner;
    [$todo, $doing, $review, $done] = $board->columns()->pluck('id')->all();

    $new = $this->actingAs($owner)->postJson("/api/v1/boards/{$board->id}/columns", ['name' => 'Blocked'])
        ->assertCreated()->assertJsonPath('data.position', 4)->json('data.id');

    $this->actingAs($owner)->patchJson("/api/v1/columns/{$new}", ['name' => 'On hold'])->assertJsonPath('data.name', 'On hold');

    $this->actingAs($owner)->putJson("/api/v1/boards/{$board->id}/columns/order", ['order' => [$done, $todo, $doing, $review, $new]])
        ->assertOk()->assertJsonPath('data.0.id', $done);

    // Partial or foreign orders are rejected.
    $this->actingAs($owner)->putJson("/api/v1/boards/{$board->id}/columns/order", ['order' => [$done, $todo]])->assertUnprocessable();

    $this->actingAs($owner)->deleteJson("/api/v1/columns/{$todo}")->assertNoContent();
    expect($board->columns()->pluck('position')->all())->toBe([0, 1, 2, 3]);
});

test('the activity feed records what happened', function () {
    $board = Board::factory()->create();
    $this->actingAs($board->owner)->postJson("/api/v1/columns/{$board->columns()->first()->id}/cards", ['title' => 'Ship it']);

    $this->actingAs($board->owner)->getJson("/api/v1/boards/{$board->id}/activity")
        ->assertOk()
        ->assertJsonPath('data.0.description', 'added "Ship it" to To do');
});
