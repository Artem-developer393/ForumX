<?php

namespace App\Models;

class Report
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $reporterId,
        public readonly int $categoryId,
        public readonly string $targetType,
        public readonly int $targetId,
        public readonly ?string $description,
        public readonly string $status = 'pending',
        public readonly ?int $reviewedBy = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id'])
                ? (int)$data['id']
                : null,
            reporterId: isset($data['reporter_id'])
                ? (int)$data['reporter_id']
                : null,
            categoryId: (int)($data['category_id'] ?? 0),
            targetType: $data['target_type'] ?? '',
            targetId: (int)($data['target_id'] ?? 0),
            description: $data['description'] ?? null,
            status: $data['status'] ?? 'pending',
            reviewedBy: isset($data['reviewed_by'])
                ? (int)$data['reviewed_by']
                : null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }
}
