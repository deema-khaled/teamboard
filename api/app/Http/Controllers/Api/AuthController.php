<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 *
 * Token-based auth (Laravel Sanctum). Send the token as `Authorization: Bearer <token>`.
 */
class AuthController extends Controller
{
    /**
     * Register
     *
     * Creates an account and returns an API token.
     *
     * @unauthenticated
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
        ]);

        return $this->tokenResponse($user, $data['device_name'] ?? 'spa', 201);
    }

    /**
     * Log in
     *
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return $this->tokenResponse($user, $data['device_name'] ?? 'spa');
    }

    /** Current user */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                ...$request->user()->toPublic(),
                'email' => $request->user()->email,
                'unread_notifications' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    /** Log out (revokes the current token) */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function tokenResponse(User $user, string $device, int $status = 200): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken($device)->plainTextToken,
            'user' => [...$user->toPublic(), 'email' => $user->email],
        ], $status);
    }
}
