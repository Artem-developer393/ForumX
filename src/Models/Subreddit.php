<?php

namespace App\Models;

class Subreddit
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly string $description,
        public readonly ?int $creatorId,
        public readonly bool $isLocked = false,
        public readonly ?string $createdAt = null,
        public readonly ?string $icon = null,
        public readonly int $subscribersCount = 0
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id'])
                ? (int)$data['id']
                : null,
            title: $data['title'],
            description: $data['description'] ?? '',
            creatorId: isset($data['by_created'])
                ? (int)$data['by_created']
                : (
                    isset($data['user_id'])
                    ? (int)$data['user_id']
                    : null
                ),
            isLocked: (bool)($data['is_locked'] ?? false),
            createdAt: $data['created_at'] ?? null,
            icon: $data['icon'] ?? null,
            subscribersCount: (int)($data['subscribers_count'] ?? 0)
        );
    }
}
