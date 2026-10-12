<?php

namespace App\DTOs\Input;

class CreatePostDTO
{
    public function __construct(
        public readonly int $subredditId,
        public readonly int $authorId,
        public readonly string $title,
        public readonly string $body,
    ) {}
    public static function fromArray(array $data, int $authorId): self
    {
        return new self(
            subredditId: (int)($data['subreddit_id'] ?? 0),
            authorId: $authorId,
            title: $data['title'] ?? '',
            body: $data['body'] ?? ''
        );
    }
}
