<?php

namespace App\DTOs\Input;

class ChangePasswordDTO
{
    public function __construct(
        public readonly string $currentPassword,
        public readonly string $newPassword
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            currentPassword: (string)($data['currentPassword'] ?? ''),
            newPassword: (string)($data['newPassword'] ?? '')
        );
    }
}
