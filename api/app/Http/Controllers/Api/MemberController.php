<?php

namespace App\Http\Controllers\Api;

use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Mail\BoardInvitation;
use App\Models\Board;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * @group Members & invitations
 */
class MemberController extends Controller
{
    /**
     * Invite someone by email (owner only)
     *
     * Sends an email with a link that is valid for 7 days.
     */
    public function invite(Request $request, Board $board): JsonResponse
    {
        Gate::authorize('manage', $board);

        $email = strtolower($request->validate(['email' => ['required', 'email', 'max:255']])['email']);

        if ($board->members()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This person is already on the board.']);
        }

        // Re-inviting replaces any earlier pending invitation.
        $board->invitations()->where('email', $email)->whereNull('accepted_at')->delete();
        [$invitation, $token] = Invitation::issue($board, $request->user(), $email);

        Mail::to($email)->queue(new BoardInvitation($invitation, $token));
        $board->log($request->user(), 'member.invited', "invited {$email}");

        return response()->json(['data' => [
            'email' => $email,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]], 201);
    }

    /**
     * Look up an invitation
     *
     * Used by the "accept invitation" page before the user signs in.
     *
     * @unauthenticated
     */
    public function showInvitation(string $token): JsonResponse
    {
        $invitation = Invitation::forToken($token)->with(['board', 'inviter'])->first();

        abort_unless($invitation?->isUsable(), 404, 'This invitation is invalid or has expired.');

        return response()->json(['data' => [
            'board' => $invitation->board->only('id', 'name', 'color'),
            'invited_by' => $invitation->inviter->name,
            'email' => $invitation->email,
        ]]);
    }

    /**
     * Accept an invitation
     *
     * The signed-in user must have the email address the invitation was sent to.
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        $board = DB::transaction(function () use ($token, $user) {
            $invitation = Invitation::forToken($token)->lockForUpdate()->first();

            abort_unless($invitation?->isUsable(), 404, 'This invitation is invalid or has expired.');
            abort_unless($invitation->email === strtolower($user->email), 403, 'This invitation was sent to a different email address.');

            $invitation->forceFill(['accepted_at' => now()])->save();
            $invitation->board->members()->syncWithoutDetaching([$user->id => ['role' => 'member']]);

            return $invitation->board;
        });

        $board->log($user, 'member.joined', 'joined the board');
        broadcast(new BoardChanged($board, 'member.joined', ['member' => [...$user->toPublic(), 'role' => 'member']], $user))->toOthers();

        return response()->json(['data' => ['board_id' => $board->id]]);
    }

    /** Remove a member, or leave the board yourself */
    public function destroy(Request $request, Board $board, User $user): Response
    {
        $leaving = $user->is($request->user());

        if (! $leaving) {
            Gate::authorize('manage', $board);
        }
        abort_if($board->isOwner($user), 422, 'The owner cannot be removed from the board.');
        abort_unless($board->hasMember($user), 404);

        $board->members()->detach($user->id);
        $board->cards()->where('assignee_id', $user->id)->update(['assignee_id' => null]);

        $board->log($request->user(), 'member.removed', $leaving ? 'left the board' : "removed {$user->name}");
        broadcast(new BoardChanged($board, 'member.removed', ['user_id' => $user->id], $request->user()))->toOthers();

        return response()->noContent();
    }
}
