<?php

namespace App\DTOs\Input;

class RegisterDTO
{
    public function __construct(
        public readonly string $login,
        public readonly string $email,
        public readonly string $password
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            login: trim($data['login'] ?? ''),
            email: filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL),
            password: $data['password'] ?? ''
        );
    }
}