<?php

namespace App\DTOs\Output;

class AdminSubredditResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $description,
        public readonly ?int $creatorId,
        public readonly ?string $creatorLogin,
        public readonly bool $isLocked,
        public readonly ?string $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            title: $data['title'],
            description: $data['description'] ?? '',
            creatorId: isset($data['by_created'])
                ? (int)$data['by_created']
                : null,
            creatorLogin: $data['creator_login'] ?? null,
            isLocked: (bool)$data['is_locked'],
            createdAt: $data['created_at'] ?? null
        );
    }
}
