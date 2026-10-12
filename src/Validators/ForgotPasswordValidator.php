<?php

namespace App\Validators;

use App\DTOs\Input\ForgotPasswordDTO;

class ForgotPasswordValidator
{
    public function validate(ForgotPasswordDTO $dto): array
    {
        $errors = [];

        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        return $errors;
    }
}
