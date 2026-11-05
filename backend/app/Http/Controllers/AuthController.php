<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate user credentials and establish a Sanctum SPA session.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            return ApiResponse::error('The provided credentials do not match our records.', 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::guard('web')->user()->load('role');

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }
            return ApiResponse::forbidden('Your account has been deactivated. Please contact an administrator.');
        }

        // Regenerate session to protect against session fixation attacks
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return ApiResponse::success([
            'user' => $user,
        ], 'Login successful.');
    }

    /**
     * Invalidate the current session and logout the authenticated user.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::forgetGuards();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    /**
     * Return the currently authenticated user with assigned role.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user()->load('role');

        return ApiResponse::success([
            'user' => $user,
        ], 'Current user profile retrieved successfully.');
    }

    /**
     * Verify current password and update to a new secure hashed password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return ApiResponse::validationError([
                'current_password' => ['The current password provided is incorrect.'],
            ], 'Current password verification failed.');
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return ApiResponse::success(null, 'Password changed successfully.');
    }
}
