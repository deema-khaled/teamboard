<?php

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Personal channel for live notifications.
Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id) => $user->id === $id);

// Presence channel per board: members only. What we return is what other
// members see in "who's online".
Broadcast::channel('board.{board}', function (User $user, Board $board) {
    return $board->hasMember($user) ? $user->toPublic() : false;
});
