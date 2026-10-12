<?php

namespace App\DTOs\Output;

use App\Models\ReportCategory;

class ReportCategoryResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $title,
        public readonly bool $requiresDescription
    ) {}

    public static function fromModel(ReportCategory $category): self
    {
        return new self(
            id: $category->id,
            code: $category->code,
            title: $category->title,
            requiresDescription: $category->requiresDescription
        );
    }
}
