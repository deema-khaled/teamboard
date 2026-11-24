<?php

use App\Mail\BoardInvitation;
use App\Models\Board;
use App\Models\Card;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\CardAssigned;
use App\Notifications\CardDueSoon;
use App\Notifications\CommentAdded;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

// --- Invitations -----------------------------------------------------------------

test('the owner can invite by email; the token is only stored hashed', function () {
    Mail::fake();
    $board = Board::factory()->create();

    $this->actingAs($board->owner)
        ->postJson("/api/v1/boards/{$board->id}/invitations", ['email' => 'New@Team.test'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'new@team.test');

    $token = null;
    Mail::assertQueued(BoardInvitation::class, function (BoardInvitation $mail) use (&$token) {
        $token = $mail->token;

        return $mail->hasTo('new@team.test');
    });

    expect(Invitation::sole()->token_hash)->toBe(hash('sha256', $token))
        ->and(Invitation::where('token_hash', $token)->exists())->toBeFalse();
});

test('members cannot invite', function () {
    $member = User::factory()->create();
    $board = Board::factory()->withMembers($member)->create();

    $this->actingAs($member)->postJson("/api/v1/boards/{$board->id}/invitations", ['email' => 'x@team.test'])->assertForbidden();
});

test('an invited user can accept and joins the board', function () {
    $board = Board::factory()->create();
    [, $token] = Invitation::issue($board, $board->owner, 'omar@team.test');
    $omar = User::factory()->create(['email' => 'omar@team.test']);

    $this->getJson("/api/v1/invitations/{$token}")->assertOk()->assertJsonPath('data.board.name', $board->name);

    $this->actingAs($omar)->postJson("/api/v1/invitations/{$token}/accept")->assertOk()->assertJsonPath('data.board_id', $board->id);
    expect($board->hasMember($omar))->toBeTrue();

    // Single use.
    $this->actingAs($omar)->postJson("/api/v1/invitations/{$token}/accept")->assertNotFound();
});

test('invitations are bound to the invited email and expire', function () {
    $board = Board::factory()->create();
    [, $token] = Invitation::issue($board, $board->owner, 'omar@team.test');

    $this->actingAs(User::factory()->create(['email' => 'someone@else.test']))
        ->postJson("/api/v1/invitations/{$token}/accept")->assertForbidden();

    $this->travel(8)->days();
    $this->getJson("/api/v1/invitations/{$token}")->assertNotFound();
});

test('the owner can remove a member, and members can leave', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $board = Board::factory()->withMembers($a, $b)->create();
    $card = Card::factory()->in($board->columns()->first())->create(['assignee_id' => $a->id]);

    $this->actingAs($b)->deleteJson("/api/v1/boards/{$board->id}/members/{$a->id}")->assertForbidden();
    $this->actingAs($board->owner)->deleteJson("/api/v1/boards/{$board->id}/members/{$a->id}")->assertNoContent();
    $this->actingAs($b)->deleteJson("/api/v1/boards/{$board->id}/members/{$b->id}")->assertNoContent();
    $this->actingAs($board->owner)->deleteJson("/api/v1/boards/{$board->id}/members/{$board->owner_id}")->assertUnprocessable();

    expect($board->members()->count())->toBe(1)
        ->and($card->fresh()->assignee_id)->toBeNull();
});

// --- Notifications ---------------------------------------------------------------

test('assigning someone notifies them, but not when you assign yourself', function () {
    Notification::fake();
    $member = User::factory()->create();
    $board = Board::factory()->withMembers($member)->create();
    $column = $board->columns()->first();

    $this->actingAs($board->owner)->postJson("/api/v1/columns/{$column->id}/cards", ['title' => 'Mine', 'assignee_id' => $board->owner_id]);
    $this->actingAs($board->owner)->postJson("/api/v1/columns/{$column->id}/cards", ['title' => 'Yours', 'assignee_id' => $member->id]);

    Notification::assertSentTo($member, CardAssigned::class, fn ($n) => $n->card->title === 'Yours');
    Notification::assertNotSentTo($board->owner, CardAssigned::class);
});

test('comments notify the creator and assignee, never the author', function () {
    Notification::fake();
    $assignee = User::factory()->create();
    $board = Board::factory()->withMembers($assignee)->create();
    $card = Card::factory()->in($board->columns()->first())->create(['assignee_id' => $assignee->id]);

    $this->actingAs($assignee)->postJson("/api/v1/cards/{$card->id}/comments", ['body' => 'Done!']);

    Notification::assertSentTo($board->owner, CommentAdded::class);
    Notification::assertNotSentTo($assignee, CommentAdded::class);
});

test('notifications can be listed and marked read', function () {
    $member = User::factory()->create();
    $board = Board::factory()->withMembers($member)->create();
    $this->actingAs($board->owner)->postJson("/api/v1/columns/{$board->columns()->first()->id}/cards", ['title' => 'Task', 'assignee_id' => $member->id]);

    $this->actingAs($member)->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('data.0.type', 'card.assigned');

    $this->actingAs($member)->postJson('/api/v1/notifications/read-all')->assertNoContent();
    $this->actingAs($member)->getJson('/api/v1/notifications')->assertJsonPath('unread', 0);
});

test('due-date reminders go out once for cards due tomorrow', function () {
    Notification::fake();
    $assignee = User::factory()->create();
    $board = Board::factory()->withMembers($assignee)->create();
    [$todo] = $board->columns()->get()->all();
    $done = $board->columns()->where('name', 'Done')->first();

    $due = Card::factory()->in($todo)->create(['assignee_id' => $assignee->id, 'due_date' => today()->addDay()]);
    Card::factory()->in($todo)->create(['assignee_id' => $assignee->id, 'due_date' => today()->addDays(3)]);
    Card::factory()->in($done)->create(['assignee_id' => $assignee->id, 'due_date' => today()->addDay()]);

    $this->artisan('cards:remind-due')->expectsOutput('Sent 1 reminder(s).');
    $this->artisan('cards:remind-due')->expectsOutput('Sent 0 reminder(s).');

    Notification::assertSentToTimes($assignee, CardDueSoon::class, 1);
    expect($due->fresh()->reminded_at)->not->toBeNull();
});
