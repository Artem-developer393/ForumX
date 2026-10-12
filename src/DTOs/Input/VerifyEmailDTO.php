<?php

namespace App\DTOs\Input;

class VerifyEmailDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $code
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: filter_var(
                trim($data['email'] ?? ''),
                FILTER_SANITIZE_EMAIL
            ),
            code: trim((string)($data['code'] ?? ''))
        );
    }
}
