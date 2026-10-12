<?php

namespace App\Validators;

use App\DTOs\Input\ResendVerificationCodeDTO;

class ResendVerificationCodeValidator
{
    public function validate(ResendVerificationCodeDTO $dto): array
    {
        $errors = [];

        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        return $errors;
    }
}
