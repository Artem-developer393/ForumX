<?php

namespace App\DTOs\Output;

use App\Models\UserModel;

class AdminUserResponseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $login,
        public readonly string $email,
        public readonly int $role,
        public readonly bool $isBanned,
        public readonly ?string $avatarUrl
    ) {}

    public static function fromModel(UserModel $user): self
    {
        return new self(
            id: $user->id,
            login: $user->login,
            email: $user->email,
            role: $user->role,
            isBanned: $user->isBanned,
            avatarUrl: self::getAvatarUrl($user->avatar)
        );
    }

    private static function getAvatarUrl(?string $avatar): ?string
    {
        if (!$avatar) {
            return null;
        }

        $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');

        return $appUrl . '/uploads/avatars/' . $avatar;
    }
}