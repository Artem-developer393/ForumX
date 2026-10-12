<?php

namespace App\DTOs\Input;

class CreateSubredditDTO
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $creatorId
    ) {}
    public static function fromArray(array $data, int $creatorId): self
    {
        return new self(
            title: trim($data['title'] ?? ''),
            description: trim($data['description'] ?? ''),
            creatorId: $creatorId
        );
    }
}
