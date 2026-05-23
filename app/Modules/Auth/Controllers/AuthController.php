<?php

namespace App\Modules\Auth\Controllers;

use App\Models\User;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;
use App\Modules\Auth\Requests\LoginRequest;

class AuthController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->auditLogService->log(
                AuditEventType::LOGIN_FAILED,
                'failed',
                'user',
                $user?->id ?? 0,
                [
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? $request->email,
                    'metadata' => [
                        'email' => $request->email,
                    ],
                    'notes' => 'Login failed due to invalid credentials.',
                ],
            );

            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        $this->auditLogService->log(
            AuditEventType::LOGIN,
            'login',
            $user,
            null,
            [
                'user' => $user,
                'notes' => 'User logged in successfully.',
            ],
        );

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }
    public function me()
    {
    return response()->json([
        'user' => auth()->user(),
    ]);
    }
    public function logout()
    {
        $user = auth()->user();
        $user?->currentAccessToken()?->delete();

        if ($user !== null) {
            $this->auditLogService->log(
                AuditEventType::LOGOUT,
                'logout',
                $user,
                null,
                [
                    'user' => $user,
                    'notes' => 'User logged out successfully.',
                ],
            );
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
}

}
