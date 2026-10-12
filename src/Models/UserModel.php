<?php

namespace App\Models;

class UserModel
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $login,
        public readonly string $email,
        public readonly string $password,
        public readonly int $role = 1,
        public readonly bool $isBanned = false,
        public readonly ?string $avatar = null,
        public readonly ?string $emailVerifiedAt = null,
        public readonly ?string $createdAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            login: $data['login'],
            email: $data['email'],
            password: $data['password'],
            role: (int)($data['role'] ?? 1),
            isBanned: (bool)($data['is_banned'] ?? false),
            avatar: $data['avatar'] ?? null,
            emailVerifiedAt: $data['email_verified_at'] ?? null,
            createdAt: $data['created_at'] ?? null
        );
    }
}
