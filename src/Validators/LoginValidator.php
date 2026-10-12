<?php

namespace App\Validators;

use App\DTOs\Input\LoginDTO;

class LoginValidator
{
    public function validate(LoginDTO $dto): array
    {
        $errors = [];

        if (empty($dto->loginOrEmail)) {
            $errors['login_or_email'] = 'Enter a username or email address';
        }

        if (empty($dto->password)) {
            $errors['password'] = 'Enter your password';
        }

        return $errors;
    }
}
