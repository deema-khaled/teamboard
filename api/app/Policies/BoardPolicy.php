<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

/**
 * Members can read and edit everything on a board; only the owner can
 * rename/delete the board and manage who is on it.
 */
class BoardPolicy
{
    public function view(User $user, Board $board): bool
    {
        return $board->hasMember($user);
    }

    public function update(User $user, Board $board): bool
    {
        return $board->hasMember($user);
    }

    public function manage(User $user, Board $board): bool
    {
        return $board->isOwner($user);
    }

    public function delete(User $user, Board $board): bool
    {
        return $board->isOwner($user);
    }
}
