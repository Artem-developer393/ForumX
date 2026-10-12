<?php

namespace App\DTOs\Input;

class UpdateCommentDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $authorId,
        public readonly int $userRole,
        public readonly string $body
    ) {}

    public static function fromArray(array $data, int $id, int $authorId, int $userRole): self {
        return new self(
            id: $id,
            authorId: $authorId,
            userRole: $userRole,
            body: trim($data['body'] ?? '')
        );
    }
}