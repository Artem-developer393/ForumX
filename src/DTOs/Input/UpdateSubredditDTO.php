<?php

namespace App\DTOs\Input;

class UpdateSubredditDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $description,
        public readonly int $userId,
        public readonly mixed $userRole
    ) {}

    public static function fromArray(array $data, int $id, int $userId, mixed $userRole): self
    {
        return new self(
            id: $id,
            title: trim($data['title'] ?? ''),
            description: trim($data['description'] ?? ''),
            userId: $userId,
            userRole: $userRole
        );
    }
}