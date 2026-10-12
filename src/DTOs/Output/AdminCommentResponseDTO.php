<?php

namespace App\DTOs\Output;

class AdminCommentResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $postId,
        public readonly string $postTitle,
        public readonly ?int $authorId,
        public readonly ?string $authorLogin,
        public readonly ?int $parentId,
        public readonly string $body,
        public readonly bool $isDeleted,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            postId: (int)$data['post_id'],
            postTitle: $data['post_title'],
            authorId: isset($data['author_id'])
                ? (int)$data['author_id']
                : null,
            authorLogin: $data['author_login'] ?? null,
            parentId: isset($data['parent_id'])
                ? (int)$data['parent_id']
                : null,
            body: (bool)$data['is_deleted']
                ? 'Comment deleted'
                : $data['body'],
            isDeleted: (bool)$data['is_deleted'],
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }
}