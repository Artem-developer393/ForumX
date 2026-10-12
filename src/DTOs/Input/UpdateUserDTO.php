<?php

namespace App\DTOs\Input;

class UpdateUserDTO
{
    public function __construct(
        public readonly string $login,
        public readonly string $email
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            login: trim($data['login'] ?? ''),
            email: filter_var(
                trim($data['email'] ?? ''),
                FILTER_SANITIZE_EMAIL
            )
        );
    }
}