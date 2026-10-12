<?php

namespace App\DTOs\Output;

use App\Models\UserModel;

class PublicUserResponseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $login,
        public readonly ?string $avatarUrl,
        public readonly ?string $createdAt,
        public readonly int $postsCount,
        public readonly int $commentsCount
    ) {}

    public static function fromModel(UserModel $userModel): self
    {
        return new self(
            id: $userModel->id,
            login: $userModel->login,
            avatarUrl: self::getAvatarUrl(
                $userModel->avatar
            ),
            createdAt: $userModel->createdAt,
            postsCount: 0,
            commentsCount: 0
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id'])
                ? (int)$data['id']
                : null,

            login: $data['login'] ?? '',

            avatarUrl: self::getAvatarUrl(
                $data['avatar'] ?? null
            ),

            createdAt: $data['created_at'] ?? null,

            postsCount: (int)($data['posts_count'] ?? 0),

            commentsCount: (int)($data['comments_count'] ?? 0)
        );
    }

    private static function getAvatarUrl(?string $avatar): ?string
    {
        if (!$avatar) {
            return null;
        }

        $appUrl = rtrim(
            $_ENV['APP_URL'] ?? '',
            '/'
        );

        return $appUrl
            . '/uploads/avatars/'
            . $avatar;
    }
}
