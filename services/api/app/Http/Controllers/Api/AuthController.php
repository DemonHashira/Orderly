<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\TokenLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $invalid = ValidationException::withMessages([
            'email' => ['Invalid credentials.'],
        ]);

        if (! Auth::attempt(
            ['email' => $data['email'], 'password' => $data['password']],
            (bool) ($data['remember'] ?? false),
        )) {
            throw $invalid;
        }

        $request->session()->regenerate();
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw $invalid;
        }

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function tokenLogin(TokenLoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->where('email', $data['email'])->first();

        $invalid = ValidationException::withMessages([
            'email' => ['Invalid credentials.'],
        ]);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw $invalid;
        }

        if (! $user->is_active) {
            throw $invalid;
        }

        $tokenName = $data['device_name'] ?? 'postman';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function tokenLogout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if (! $user || ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['New password must be different from current password.'],
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
        ])->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
