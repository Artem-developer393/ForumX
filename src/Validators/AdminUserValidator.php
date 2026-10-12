<?php

namespace App\Validators;

use App\DTOs\Input\AdminUpdateUserRoleDTO;

class AdminUserValidator
{
    public function validateRole(AdminUpdateUserRoleDTO $dto): array
    {
        $errors = [];

        if (!in_array($dto->role, [1, 2], true)) {
            $errors['role'] = 'Invalid user role';
        }

        return $errors;
    }
}