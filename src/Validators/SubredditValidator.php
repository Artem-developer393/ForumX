<?php

namespace App\Validators;

use App\DTOs\Input\CreateSubredditDTO;
use App\DTOs\Input\UpdateSubredditDTO;

class SubredditValidator
{
    public function validate(CreateSubredditDTO $dto): array
    {
        $errors = [];

        $title = trim($dto->title ?? '');
        $description = trim($dto->description ?? '');

        if ($title === '') {
            $errors['name'] = 'Subreddit name is required';
        } elseif (mb_strlen($title) < 3 || mb_strlen($title) > 50) {
            $errors['name'] = 'Subreddit name must be between 3 and 50 characters';
        } elseif (!preg_match('/^[\p{L}0-9_ ]+$/u', $title)) {
            $errors['name'] = 'Subreddit name may contain only letters, digits, spaces and underscores';
        }

        if ($description === '') {
            $errors['description'] = 'Subreddit description is required';
        }

        return $errors;
    }

    public function validateUpdate(UpdateSubredditDTO $dto): array
    {
        $errors = [];

        if (empty($dto->title)) {
            $errors['name'] = 'Subreddit name is required';
        } elseif (mb_strlen($dto->title) < 3 || mb_strlen($dto->title) > 50) {
            $errors['name'] = 'Subreddit name must be between 3 and 50 characters';
        } elseif (!preg_match('/^[\p{L}0-9_ ]+$/u', $dto->title)) {
            $errors['name'] = 'Subreddit name may contain only letters, digits, spaces and underscores';
        }
        if (empty($dto->description)) {
            $errors['description'] = 'Subreddit description is required';
        }

        return $errors;
    }
}
