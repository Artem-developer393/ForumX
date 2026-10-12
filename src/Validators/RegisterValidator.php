<?php

namespace App\Validators;

use App\DTOs\Input\RegisterDTO;

class RegisterValidator
{
    public function validate(RegisterDTO $dto): array
    {
        $errors = [];

        if (empty($dto->login)) {
            $errors['login'] = 'Login is required';
        } elseif (mb_strlen($dto->login) < 3) {
            $errors['login'] = 'Login must be at least 3 characters';
        } elseif (mb_strlen($dto->login) > 32) {
            $errors['login'] = 'Login must not exceed 32 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $dto->login)) {
            $errors['login'] = 'Login may contain only Latin letters, digits, underscores and hyphens';
        }

        if (empty($dto->email)) {
            $errors['email'] = 'Email is required';
        } elseif (strlen($dto->email) > 254) {
            $errors['email'] = 'Email must not exceed 254 characters';
        } elseif (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($dto->password)) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($dto->password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        } elseif (strlen($dto->password) > 128) {
            $errors['password'] = 'Password must not exceed 128 characters';
        } elseif (!preg_match('/[a-z]/', $dto->password)) {
            $errors['password'] = 'Password must contain a lowercase letter';
        } elseif (!preg_match('/[A-Z]/', $dto->password)) {
            $errors['password'] = 'Password must contain an uppercase letter';
        } elseif (!preg_match('/[0-9]/', $dto->password)) {
            $errors['password'] = 'Password must contain a digit';
        } elseif (!preg_match('/[^a-zA-Z0-9]/', $dto->password)) {
            $errors['password'] = 'Password must contain a special character';
        }

        return $errors;
    }
}
