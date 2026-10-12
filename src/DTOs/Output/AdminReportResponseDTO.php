<?php

namespace App\DTOs\Output;

class AdminReportResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $reporterId,
        public readonly ?string $reporterLogin,

        public readonly int $categoryId,
        public readonly string $categoryCode,
        public readonly string $categoryTitle,

        public readonly string $targetType,
        public readonly int $targetId,

        public readonly ?string $description,
        public readonly string $status,

        public readonly ?int $reviewedBy,
        public readonly ?string $reviewerLogin,

        public readonly ?string $createdAt,
        public readonly ?string $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],

            reporterId: isset($data['reporter_id'])
                ? (int)$data['reporter_id']
                : null,

            reporterLogin: $data['reporter_login'] ?? null,

            categoryId: (int)$data['category_id'],
            categoryCode: $data['category_code'],
            categoryTitle: $data['category_title'],

            targetType: $data['target_type'],
            targetId: (int)$data['target_id'],

            description: $data['description'] ?? null,
            status: $data['status'],

            reviewedBy: isset($data['reviewed_by'])
                ? (int)$data['reviewed_by']
                : null,

            reviewerLogin: $data['reviewer_login'] ?? null,

            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }
}
