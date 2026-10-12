<?php

namespace App\Validators;

use App\DTOs\Input\VerifyEmailDTO;

class VerifyEmailValidator
{
    public function validate(VerifyEmailDTO $dto): array
    {
        $errors = [];

        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (!preg_match('/^\d{6}$/', $dto->code)) {
            $errors['code'] = 'Code must contain exactly 6 digits';
        }

        return $errors;
    }
}
