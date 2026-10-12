<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JWTService
{
    private string $key;
    private string $issuer;

    public function __construct()
    {
        $this->key = $_ENV['JWT_SECRET'];
        $this->issuer = $_ENV['JWT_ISSUER'];
    }

    public function generateToken(object $user, string $familyId): string
    {
        $issuedAt = time();
        $expire = $issuedAt + (2 * 60 * 60);

        $payload = [
            'iat' => $issuedAt,
            'iss' => $this->issuer,
            'exp' => $expire,
            'data' => [
                'userId' => $user->id,
                'familyId' => $familyId
            ]
        ];

        return JWT::encode($payload, $this->key, 'HS256');
    }

    public function validateToken(string $jwt): ?object
    {
        try {
            $decoded = JWT::decode($jwt, new Key($this->key, 'HS256'));

            if (($decoded->iss ?? null) !== $this->issuer) {
                return null;
            }

            if (!isset($decoded->data->userId, $decoded->data->familyId) || (int)$decoded->data->userId <= 0 || !is_string($decoded->data->familyId) || !preg_match('/^[a-f0-9]{32}$/D', $decoded->data->familyId)) {
                return null;
            }

            return $decoded->data;
        } catch (Exception) {
            return null;
        }
    }
}
