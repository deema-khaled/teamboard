<?php

namespace App\Http\Controllers\Api;

use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Card;
use App\Models\Comment;
use App\Notifications\CommentAdded;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * @group Comments
 */
class CommentController extends Controller
{
    /** Comment on a card */
    public function store(Request $request, Card $card): JsonResponse
    {
        Gate::authorize('update', $card->board);

        $comment = $card->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validate(['body' => ['required', 'string', 'max:5000']])['body'],
        ])->load('user');

        // Tell the card's creator and assignee, but never the person commenting.
        $recipients = collect([$card->creator, $card->assignee])->filter()
            ->unique('id')
            ->reject(fn ($u) => $u->id === $request->user()->id);
        Notification::send($recipients, new CommentAdded($comment, $card));

        broadcast(new BoardChanged($card->board, 'comment.created', [
            'card_id' => $card->id,
            'comment' => (new CommentResource($comment))->resolve(),
            'comments_count' => $card->comments()->count(),
        ], $request->user()))->toOthers();

        return (new CommentResource($comment))->response()->setStatusCode(201);
    }

    /** Delete your own comment */
    public function destroy(Request $request, Comment $comment): Response
    {
        abort_unless($comment->user_id === $request->user()->id, 403, 'You can only delete your own comments.');

        $card = $comment->card;
        $comment->delete();

        broadcast(new BoardChanged($card->board, 'comment.deleted', [
            'card_id' => $card->id,
            'id' => $comment->id,
            'comments_count' => $card->comments()->count(),
        ], $request->user()))->toOthers();

        return response()->noContent();
    }
}
