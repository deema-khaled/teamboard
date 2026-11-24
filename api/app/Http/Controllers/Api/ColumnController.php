<?php

namespace App\Http\Controllers\Api;

use App\Events\BoardChanged;
use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Column;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @group Columns
 */
class ColumnController extends Controller
{
    /** Add a column (at the end) */
    public function store(Request $request, Board $board): JsonResponse
    {
        Gate::authorize('update', $board);

        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);

        $column = $board->columns()->create([
            'name' => $data['name'],
            'position' => (int) $board->columns()->max('position') + 1,
        ]);

        $this->broadcastColumns($board, 'column.created', $request);
        $board->log($request->user(), 'column.created', "added the list \"{$column->name}\"");

        return response()->json(['data' => $this->present($column)], 201);
    }

    /** Rename a column */
    public function update(Request $request, Column $column): JsonResponse
    {
        Gate::authorize('update', $column->board);

        $column->update($request->validate(['name' => ['required', 'string', 'max:60']]));
        $this->broadcastColumns($column->board, 'column.updated', $request);

        return response()->json(['data' => $this->present($column)]);
    }

    /**
     * Reorder columns
     *
     * Send every column id of the board in the new order.
     */
    public function reorder(Request $request, Board $board): JsonResponse
    {
        Gate::authorize('update', $board);

        $ids = $board->columns()->pluck('id')->all();
        $data = $request->validate([
            'order' => ['required', 'array', 'size:'.count($ids)],
            'order.*' => ['integer', 'distinct', Rule::in($ids)],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['order'] as $position => $id) {
                Column::whereKey($id)->update(['position' => $position]);
            }
        });

        $this->broadcastColumns($board, 'column.reordered', $request);

        return response()->json(['data' => $board->columns()->get()->map(fn ($c) => $this->present($c))]);
    }

    /** Delete a column and its cards */
    public function destroy(Request $request, Column $column): Response
    {
        $board = $column->board;
        Gate::authorize('update', $board);

        $name = $column->name;
        $column->delete();
        // Close the gap left by the deleted column.
        foreach ($board->columns()->get() as $i => $remaining) {
            if ($remaining->position !== $i) {
                $remaining->update(['position' => $i]);
            }
        }

        $this->broadcastColumns($board, 'column.deleted', $request, ['id' => $column->id]);
        $board->log($request->user(), 'column.deleted', "deleted the list \"{$name}\"");

        return response()->noContent();
    }

    /** Column changes are rare, so others simply get the full column list. */
    private function broadcastColumns(Board $board, string $type, Request $request, array $extra = []): void
    {
        broadcast(new BoardChanged($board, $type, [
            ...$extra,
            'columns' => $board->columns()->get()->map(fn ($c) => $this->present($c))->all(),
        ], $request->user()))->toOthers();
    }

    /** @return array<string, mixed> */
    private function present(Column $column): array
    {
        return ['id' => $column->id, 'name' => $column->name, 'position' => $column->position];
    }
}
