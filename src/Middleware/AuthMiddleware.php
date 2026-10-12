<?php

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Repositories\RefreshTokenRepository;
use App\Services\JWTService;

class AuthMiddleware
{
    private JWTService $jwtService;
    private UserRepository $userRepository;
    private RefreshTokenRepository $refreshTokenRepository;

    public const ROLE_USER = 1;
    public const ROLE_ADMIN = 2;

    public function __construct()
    {
        $this->jwtService = new JWTService();
        $this->userRepository = new UserRepository();
        $this->refreshTokenRepository = new RefreshTokenRepository();
    }

    public function handle(Request $request, ?string $requiredRole): void
    {
        if ($requiredRole === 'guest' || $requiredRole === null) {
            return;
        }

        $token = $request->getBearerToken();
        if (!$token) {
            Response::error("Authentication token is missing", 401);
        }

        $tokenData = $this->jwtService->validateToken($token);
        if (!$tokenData) {
            Response::error("Invalid or expired authentication token", 401);
        }

        $user = $this->userRepository->findById((int)$tokenData->userId);
        if (!$user) {
            Response::error("User not found", 401);
        }

        if ($user->isBanned) {
            Response::error("Your account has been banned", 403, 'ACCOUNT_BANNED');
        }

        if (!$this->refreshTokenRepository->isSessionActive((int)$user->id, $tokenData->familyId)) {
            Response::error('Session has expired or was revoked', 401);
        }

        $request->user = $user;
        $request->sessionFamilyId = $tokenData->familyId;

        if ($requiredRole === 'admin' && $user->role !== self::ROLE_ADMIN) {
            Response::error("You do not have admin rights", 403);
        }
    }
}
