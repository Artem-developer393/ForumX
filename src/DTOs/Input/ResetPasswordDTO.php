<?php

namespace App\DTOs\Input;

class ResetPasswordDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $code,
        public readonly string $newPassword
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: filter_var(
                trim($data['email'] ?? ''),
                FILTER_SANITIZE_EMAIL
            ),
            code: trim((string)($data['code'] ?? '')),
            newPassword: (string)($data['newPassword'] ?? '')
        );
    }
}
