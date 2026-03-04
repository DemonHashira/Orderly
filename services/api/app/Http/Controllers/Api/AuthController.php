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
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

final class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $remember = (bool) ($data['remember'] ?? false);

        $invalid = ValidationException::withMessages([
            'email' => ['Invalid credentials.'],
        ]);

        if (! Auth::attempt(
            ['email' => $data['email'], 'password' => $data['password']],
            $remember,
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

        $response = response()->json([
            'user' => new UserResource($user),
        ]);

        if (! $remember) {
            $response->headers->setCookie(Cookie::forget(
                Auth::guard('web')->getRecallerName(),
                config('session.path', '/'),
                config('session.domain'),
            ));
        }

        return $response;
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, ResponseAlias::HTTP_UNAUTHORIZED, 'Unauthenticated.');

        return response()->json([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentAccessToken = $user?->currentAccessToken();
        if ($currentAccessToken instanceof PersonalAccessToken) {
            $currentAccessToken->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $response = response()->json([
            'message' => 'Logged out.',
        ]);

        $response->headers->setCookie(Cookie::forget(
            Auth::guard('web')->getRecallerName(),
            config('session.path', '/'),
            config('session.domain'),
        ));
        $response->headers->setCookie(Cookie::forget(
            config('session.cookie'),
            config('session.path', '/'),
            config('session.domain'),
        ));

        return $response;
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
