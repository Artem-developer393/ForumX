<?php

namespace App\DTOs\Input;

class AdminUpdateUserRoleDTO
{
    public function __construct(
        public readonly int $role
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            role: (int)($data['role'] ?? 0)
        );
    }
}