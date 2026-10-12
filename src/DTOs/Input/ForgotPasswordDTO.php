<?php

namespace App\DTOs\Input;

class ForgotPasswordDTO
{
    public function __construct(
        public readonly string $email
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: filter_var(
                trim($data['email'] ?? ''),
                FILTER_SANITIZE_EMAIL
            )
        );
    }
}
