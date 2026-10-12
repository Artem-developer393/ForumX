<?php

namespace App\Services;

use App\Repositories\RefreshTokenRepository;

class RefreshTokenService
{
    private RefreshTokenRepository $refreshTokenRepository;

    public function __construct()
    {
        $this->refreshTokenRepository = new RefreshTokenRepository();
    }

    public function generateToken(int $userId): array
    {
        $token = bin2hex(random_bytes(32));
        $now = time();
        $absolute = $now + (90 * 24 * 60 * 60);
        $familyId = bin2hex(random_bytes(16));
        $saved = $this->refreshTokenRepository->save(
            $userId,
            hash('sha256', $token),
            gmdate('Y-m-d H:i:s', min($now + (30 * 24 * 60 * 60), $absolute)),
            $familyId,
            gmdate('Y-m-d H:i:s', $absolute)
        );
        if (!$saved) {
            throw new \RuntimeException('Unable to create refresh token');
        }
        return ['refresh_token' => $token, 'family_id' => $familyId];
    }

    public function refresh(string $oldToken): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $oldToken)) {
            return null;
        }
        $newToken = bin2hex(random_bytes(32));
        $session = $this->refreshTokenRepository->rotate(
            hash('sha256', $oldToken),
            hash('sha256', $newToken),
            gmdate('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60))
        );
        if ($session === null) {
            return null;
        }
        return ['user_id' => $session['user_id'], 'family_id' => $session['family_id'], 'refresh_token' => $newToken];
    }


    public function logout(int $userId, string $familyId, string $refreshToken): void
    {
        if (!$this->refreshTokenRepository->revokeSession($userId, $familyId, hash('sha256', $refreshToken))) {
            throw new \InvalidArgumentException('Invalid session refresh token');
        }
    }

    public function logoutAll(int $userId): void
    {
        $this->refreshTokenRepository->revokeAll($userId);
    }
}
