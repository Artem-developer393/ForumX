<?php

namespace App\DTOs\Output;

use App\Models\Comment;

class CommentResponseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $postId,
        public readonly ?CommentAuthorResponseDTO $author,
        public readonly ?int $parentId,
        public readonly string $body,
        public readonly bool $isDeleted,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly array $replies = []
    ) {}

    public static function fromModel(
        Comment $comment,
        ?CommentAuthorResponseDTO $author,
        array $replies = []
    ): self {
        return new self(
            id: $comment->id,
            postId: $comment->postId,
            author: $author,
            parentId: $comment->parentId,
            body: $comment->isDeleted ? 'Comment deleted' : $comment->body,
            isDeleted: $comment->isDeleted,
            createdAt: $comment->createdAt,
            updatedAt: $comment->updatedAt,
            replies: $replies
        );
    }
}
