<?php

namespace App\DTOs\Input;

class CreateReportDTO
{
    public function __construct(
        public readonly int $reporterId,
        public readonly string $targetType,
        public readonly int $targetId,
        public readonly int $categoryId,
        public readonly ?string $description
    ) {}

    public static function fromArray(array $data, int $reporterId): self
    {
        return new self(
            reporterId: $reporterId,
            targetType: trim($data['targetType'] ?? ''),
            targetId: (int)($data['targetId'] ?? 0),
            categoryId: (int)($data['categoryId'] ?? 0),
            description: isset($data['description'])
                ? trim($data['description'])
                : null
        );
    }
}
