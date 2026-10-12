<?php

namespace App\DTOs\Output;

use App\Models\UserModel;

class CommentAuthorResponseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $login,
        public readonly ?string $avatarUrl
    ) {}

    public static function fromModel(UserModel $user): self
    {
        return new self(
            id: $user->id,
            login: $user->login,
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
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['author_id'])
                ? (int)$data['author_id']
                : null,

            login: $data['author_login'] ?? 'Deleted user',

            avatarUrl: self::getAvatarUrl(
                $data['author_avatar'] ?? null
            )
        );
    }
}
