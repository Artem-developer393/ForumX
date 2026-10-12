<?php

namespace App\Validators;

use App\DTOs\Input\CreateReportDTO;

class ReportValidator
{
    public function validateCreate(CreateReportDTO $dto): array
    {
        $errors = [];

        $allowedTargetTypes = [
            'post',
            'comment',
            'subreddit',
            'user'
        ];

        if (!in_array(
            $dto->targetType,
            $allowedTargetTypes,
            true
        )) {
            $errors['targetType'] = 'Invalid report target type';
        }

        if ($dto->targetId <= 0) {
            $errors['targetId'] = 'Invalid report target ID';
        }

        if ($dto->categoryId <= 0) {
            $errors['categoryId'] = 'Invalid report category';
        }

        if (
            $dto->description !== null && mb_strlen($dto->description) > 1000
        ) {
            $errors['description'] = 'Description must not exceed 1,000 characters';
        }

        return $errors;
    }
}
