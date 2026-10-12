<?php

namespace App\DTOs\Output;

use App\Models\Report;

class ReportResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $categoryId,
        public readonly string $targetType,
        public readonly int $targetId,
        public readonly ?string $description,
        public readonly string $status,
        public readonly ?string $createdAt
    ) {}

    public static function fromModel(Report $report): self
    {
        return new self(
            id: $report->id,
            categoryId: $report->categoryId,
            targetType: $report->targetType,
            targetId: $report->targetId,
            description: $report->description,
            status: $report->status,
            createdAt: $report->createdAt
        );
    }
}
