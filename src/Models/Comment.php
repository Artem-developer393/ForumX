<?php

namespace App\Models;

class Comment
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $postId,
        public readonly ?int $authorId,
        public readonly ?int $parentId,
        public readonly string $body,
        public readonly bool $isDeleted = false,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            postId: (int)($data['post_id'] ?? 0),
            authorId: isset($data['author_id'])
                ? (int)$data['author_id']
                : null,
            parentId: isset($data['parent_id'])
                ? (int)$data['parent_id']
                : null,
            body: $data['body'] ?? '',
            isDeleted: (bool)($data['is_deleted'] ?? false),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }
}
