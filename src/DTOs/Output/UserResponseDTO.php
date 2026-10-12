<?php

namespace App\DTOs\Output;

use App\Models\UserModel;


class UserResponseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $login,
        public readonly string $email,
        public readonly ?string $avatarUrl,
        public readonly ?string $createdAt,
        public readonly int $postsCount,
        public readonly int $commentsCount
    ) {}
    public static function fromModelWithProfile(UserModel $userModel, array $profile): self
    {
        return new self(
            id: $userModel->id,
            login: $userModel->login,
            email: $userModel->email,
            avatarUrl: self::getAvatarUrl($userModel->avatar),
            createdAt: $profile['created_at'] ?? null,
            postsCount: (int)($profile['posts_count'] ?? 0),
            commentsCount: (int)($profile['comments_count'] ?? 0)
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
