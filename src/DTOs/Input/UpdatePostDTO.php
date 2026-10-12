<?php

namespace App\DTOs\Input;

class UpdatePostDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $creatorId,
        public readonly string $title,
        public readonly string $body,
        public mixed $userRole
    ) {}

    public static function fromArray(array $data, int $id, int $creatorId, mixed $userRole): self
    {
        return new self(
            id: $id,
            creatorId: $creatorId,
            title: trim($data['title'] ?? ''),
            body: trim($data['body'] ?? ''),
            userRole: $userRole
        );
    }
}
