<?php

namespace App\Models;

class Post
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $subredditId,
        public readonly ?int $creatorId,
        public readonly string $title,
        public readonly string $body,
        public readonly bool $isLocked,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly ?string $image = null,
        public readonly ?string $authorLogin = null,
        public readonly ?string $authorAvatar = null,
        public readonly ?string $subredditTitle = null,
        public readonly int $commentsCount = 0
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            subredditId: (int)($data['subreddit_id'] ?? 0),
            creatorId: isset($data['author_id'])
                ? (int)$data['author_id']
                : null,
            title: $data['title'] ?? '',
            body: $data['body'] ?? '',
            isLocked: (bool)($data['is_locked'] ?? false),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            image: $data['image'] ?? null,

            authorLogin: $data['author_login'] ?? null,
            authorAvatar: $data['author_avatar'] ?? null,
            subredditTitle: $data['subreddit_title'] ?? null,
            commentsCount: (int)($data['comments_count'] ?? 0)
        );
    }
}