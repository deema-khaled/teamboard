<?php

namespace App\Http\Controllers\Api;

use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\Column;
use App\Notifications\CardAssigned;
use App\Services\CardMover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @group Cards
 */
class CardController extends Controller
{
    public function __construct(private readonly CardMover $mover) {}

    /** Create a card at the bottom of a column */
    public function store(Request $request, Column $column): JsonResponse
    {
        $board = $column->board;
        Gate::authorize('update', $board);

        $data = $this->validated($request, $board->id);

        $card = new Card($data);
        $card->board()->associate($board);
        $card->column()->associate($column);
        $card->created_by = $request->user()->id;
        $card->position = $this->mover->nextPosition($column);
        $card->save();

        $this->notifyAssignee($card, null, $request);
        $board->log($request->user(), 'card.created', "added \"{$card->title}\" to {$column->name}");
        $this->broadcastCard($card, 'card.created', $request);

        return (new CardResource($this->fresh($card)))->response()->setStatusCode(201);
    }

    /** Get a card with its comments */
    public function show(Card $card): CardResource
    {
        Gate::authorize('view', $card->board);

        return new CardResource($card->load(['assignee', 'comments.user'])->loadCount('comments'));
    }

    /** Update a card's details */
    public function update(Request $request, Card $card): CardResource
    {
        Gate::authorize('update', $card->board);

        $previousAssignee = $card->assignee_id;
        $card->update($this->validated($request, $card->board_id, partial: true));

        $this->notifyAssignee($card, $previousAssignee, $request);
        $this->broadcastCard($card, 'card.updated', $request);

        return new CardResource($this->fresh($card));
    }

    /**
     * Move a card
     *
     * Moves the card to `column_id` at `position` (0 = top). Positions of the
     * other cards are adjusted. The response has the new order of every
     * column that changed.
     */
    public function move(Request $request, Card $card): JsonResponse
    {
        $board = $card->board;
        Gate::authorize('update', $board);

        $data = $request->validate([
            'column_id' => ['required', 'integer', Rule::exists('columns', 'id')->where('board_id', $board->id)],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $from = $card->column;
        $to = Column::findOrFail($data['column_id']);
        $orders = $this->mover->move($card, $to, $data['position']);

        if ($from->id !== $to->id) {
            $board->log($request->user(), 'card.moved', "moved \"{$card->title}\" from {$from->name} to {$to->name}");
        }

        broadcast(new BoardChanged($board, 'card.moved', [
            'card' => (new CardResource($this->fresh($card)))->resolve(),
            'orders' => $orders,
        ], $request->user()))->toOthers();

        return response()->json(['data' => ['orders' => $orders]]);
    }

    /** Delete a card */
    public function destroy(Request $request, Card $card): Response
    {
        $board = $card->board;
        Gate::authorize('update', $board);

        $card->delete();
        $this->mover->resequenceColumn($card->column_id);

        $board->log($request->user(), 'card.deleted', "deleted \"{$card->title}\"");
        broadcast(new BoardChanged($board, 'card.deleted', ['id' => $card->id, 'column_id' => $card->column_id], $request->user()))->toOthers();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $boardId, bool $partial = false): array
    {
        return $request->validate([
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'label' => ['nullable', Rule::in(Card::LABELS)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            // Only members of this board can be assigned.
            'assignee_id' => ['nullable', 'integer', Rule::exists('board_user', 'user_id')->where('board_id', $boardId)],
        ]);
    }

    private function notifyAssignee(Card $card, ?int $previous, Request $request): void
    {
        if ($card->assignee_id && $card->assignee_id !== $previous && $card->assignee_id !== $request->user()->id) {
            $card->assignee->notify(new CardAssigned($card, $request->user()));
        }
    }

    private function broadcastCard(Card $card, string $type, Request $request): void
    {
        broadcast(new BoardChanged($card->board, $type, [
            'card' => (new CardResource($this->fresh($card)))->resolve(),
        ], $request->user()))->toOthers();
    }

    private function fresh(Card $card): Card
    {
        return $card->load('assignee')->loadCount('comments');
    }
}
