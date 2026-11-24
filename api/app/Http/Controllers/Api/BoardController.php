<?php

namespace App\Http\Controllers\Api;

use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\BoardResource;
use App\Models\Board;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @group Boards
 */
class BoardController extends Controller
{
    /** List my boards */
    public function index(Request $request): AnonymousResourceCollection
    {
        $boards = $request->user()->boards()
            ->with('members')
            ->withCount('cards')
            ->latest('boards.updated_at')
            ->get();

        return BoardResource::collection($boards);
    }

    /**
     * Create a board
     *
     * The board starts with four columns: To do, In progress, Review, Done.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $board = new Board($data);
        $board->owner_id = $request->user()->id;
        $board->save();
        $board->log($request->user(), 'board.created', 'created the board');

        return (new BoardResource($this->loadFull($board)))->response()->setStatusCode(201);
    }

    /** Get a board with its columns, cards and members */
    public function show(Board $board): BoardResource
    {
        Gate::authorize('view', $board);

        return new BoardResource($this->loadFull($board));
    }

    /** Update a board (owner only) */
    public function update(Request $request, Board $board): BoardResource
    {
        Gate::authorize('manage', $board);

        $board->update($this->validated($request, partial: true));
        broadcast(new BoardChanged($board, 'board.updated', $board->only('name', 'description', 'color'), $request->user()))->toOthers();

        return new BoardResource($board->load('members'));
    }

    /** Delete a board (owner only) */
    public function destroy(Request $request, Board $board): Response
    {
        Gate::authorize('delete', $board);

        broadcast(new BoardChanged($board, 'board.deleted', [], $request->user()))->toOthers();
        $board->delete();

        return response()->noContent();
    }

    /** Recent activity on a board */
    public function activity(Board $board): JsonResponse
    {
        Gate::authorize('view', $board);

        return response()->json([
            'data' => $board->activities()->with('user')->limit(30)->get()->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'description' => $a->description,
                'user' => $a->user?->toPublic(),
                'created_at' => $a->created_at->toIso8601String(),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['sometimes', Rule::in(Board::COLORS)],
        ]);
    }

    private function loadFull(Board $board): Board
    {
        return $board->load([
            'members',
            'columns.cards' => fn ($q) => $q->with('assignee')->withCount('comments'),
        ]);
    }
}
