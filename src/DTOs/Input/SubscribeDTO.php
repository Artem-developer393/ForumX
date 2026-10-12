<?php

namespace App\DTOs\Input;

class SubscribeDTO
{
    public function __construct(
        public readonly int $subredditId,
        public readonly int $userId
    ) {}

    public static function fromArray(int $subredditId, int $userId): self{
        return new self(
            subredditId: $subredditId,
            userId: $userId
        );
    }
}
