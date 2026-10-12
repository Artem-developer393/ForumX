<?php
namespace App\DTOs\Input;

class LoginDTO
{
    public function __construct(
        public readonly string $loginOrEmail,
        public readonly string $password,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            loginOrEmail: trim($data['login_or_email'] ?? $data['login'] ?? ''),
            password: $data['password'] ?? ''
        );
    }
}