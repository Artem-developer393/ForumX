<?php

namespace App\DTOs\Output;

class PostAuthorResponseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $login,
        public readonly ?string $avatarUrl
    ) {}

    public static function fromData(
        ?int $id,
        ?string $login,
        ?string $avatar
    ): ?self {
        if ($id === null) {
            return null;
        }

        return new self(
            id: $id,
            login: $login ?? 'Deleted user',
            avatarUrl: self::getAvatarUrl($avatar)
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
