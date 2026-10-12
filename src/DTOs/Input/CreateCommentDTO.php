<?php

namespace App\DTOs\Input;

class CreateCommentDTO
{
    public function __construct(
        public readonly int $postId,
        public readonly int $authorId,
        public readonly ?int $parentId,
        public readonly string $body
    ) {}

    public static function fromArray(array $data, int $postId, int $authorId): self {
        return new self(
            postId: $postId,
            authorId: $authorId,
            parentId: isset($data['parentId'])
                ? (int)$data['parentId']
                : null,
            body: trim($data['body'] ?? '')
        );
    }
}
