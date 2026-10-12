<?php

namespace App\DTOs\Output;

class AdminPostResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $subredditId,
        public readonly string $subredditTitle,
        public readonly ?int $authorId,
        public readonly ?string $authorLogin,
        public readonly string $title,
        public readonly string $body,
        public readonly bool $isLocked,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            subredditId: (int)$data['subreddit_id'],
            subredditTitle: $data['subreddit_title'],
            authorId: isset($data['author_id'])
                ? (int)$data['author_id']
                : null,
            authorLogin: $data['author_login'] ?? null,
            title: $data['title'],
            body: $data['body'],
            isLocked: (bool)$data['is_locked'],
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }
}