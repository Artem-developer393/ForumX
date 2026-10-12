<?php

namespace App\Models;

class ReportCategory
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $code,
        public readonly string $title,
        public readonly bool $requiresDescription,
        public readonly bool $isActive
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id'])
                ? (int)$data['id']
                : null,
            code: $data['code'] ?? '',
            title: $data['title'] ?? '',
            requiresDescription: (bool)($data['requires_description'] ?? false),
            isActive: (bool)($data['is_active'] ?? true)
        );
    }
}