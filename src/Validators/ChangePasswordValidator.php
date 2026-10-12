<?php

namespace App\Validators;

use App\DTOs\Input\ChangePasswordDTO;

class ChangePasswordValidator
{
    public function validate(ChangePasswordDTO $dto): array
    {
        $errors = [];

        if ($dto->currentPassword === '') {
            $errors['currentPassword'] =
                'Current password is required';
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