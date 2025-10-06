<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        // Get validated data from the request
        $credentials = $request->validated();

        // Attempt Login
        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Get the authenticated user
        $user = Auth::user();

        // Create a Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'balance'])
        ]);
    }

    public function whoami(Request $request): JsonResponse|array
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $user->only(['id', 'name', 'email', 'balance']);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Revoke the current user's token
        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
