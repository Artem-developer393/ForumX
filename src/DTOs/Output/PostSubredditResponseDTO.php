<?php

namespace App\DTOs\Output;

class PostSubredditResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $title
    ) {}
}
