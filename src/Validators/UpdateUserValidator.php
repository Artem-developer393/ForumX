<?php

namespace App\Validators;

use App\DTOs\Input\UpdateUserDTO;

class UpdateUserValidator
{
    public function validate(UpdateUserDTO $dto): array
    {
        $errors = [];

        if (empty($dto->login) || strlen($dto->login) < 3) {
            $errors['login'] = 'Username must be at least 3 characters';
        } elseif (strlen($dto->login) > 32) {
            $errors['login'] = 'Username must not exceed 32 characters';
        }

        if (empty($dto->email)) {
            $errors['email'] = 'Email cannot be empty';
        } elseif (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        return $errors;
    }
}