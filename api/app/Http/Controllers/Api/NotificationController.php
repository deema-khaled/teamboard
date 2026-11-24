<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @group Notifications
 *
 * New notifications are also pushed live on the private channel `App.Models.User.{id}`.
 */
class NotificationController extends Controller
{
    /** List my latest notifications */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->notifications()->limit(30)->get()->map(fn ($n) => [
                'id' => $n->id,
                ...$n->data,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->toIso8601String(),
            ]),
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** Mark all as read */
    public function readAll(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
