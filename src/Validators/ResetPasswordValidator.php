<?php

namespace App\Validators;

use App\DTOs\Input\ResetPasswordDTO;

class ResetPasswordValidator
{
    public function validate(ResetPasswordDTO $dto): array
    {
        $errors = [];

        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (!preg_match('/^\d{6}$/', $dto->code)) {
            $errors['code'] = 'Code must contain exactly 6 digits';
        }

        if (strlen($dto->newPassword) < 8) {
            $errors['newPassword'] = 'Password must be at least 8 characters';
        } elseif (strlen($dto->newPassword) > 128) {
            $errors['newPassword'] = 'Password must not exceed 128 characters';
        } elseif (!preg_match('/[a-z]/', $dto->newPassword)) {
            $errors['newPassword'] = 'Password must contain a lowercase letter';
        } elseif (!preg_match('/[A-Z]/', $dto->newPassword)) {
            $errors['newPassword'] = 'Password must contain an uppercase letter';
        } elseif (!preg_match('/[0-9]/', $dto->newPassword)) {
            $errors['newPassword'] = 'Password must contain a digit';
        } elseif (!preg_match('/[^a-zA-Z0-9]/', $dto->newPassword)) {
            $errors['newPassword'] = 'Password must contain a special character';
        }

        return $errors;
    }
}