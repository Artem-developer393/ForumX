<?php

namespace App\DTOs\Output;

class PaginatedResponseDTO
{
    public function __construct(
        public readonly array $data,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit
    ) {}
}