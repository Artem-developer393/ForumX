<?php

namespace App\Validators;

use App\DTOs\Input\CreatePostDTO;
use App\DTOs\Input\UpdatePostDTO;

class PostValidator
{
    public function validate(CreatePostDTO $dto): array
    {
        $errors = [];

        if ($dto->subredditId <= 0) {
            $errors['subreddit_id'] = 'Specify a valid subreddit';
        }

        if (empty($dto->title)) {
            $errors['title'] = 'Post title is required';
        } elseif (mb_strlen($dto->title) < 3 || mb_strlen($dto->title) > 100) {
            $errors['title'] = 'Title must be between 3 and 100 characters';
        }

        if (empty($dto->body)) {
            $errors['body'] = 'Post body is required';
        } elseif (mb_strlen($dto->body) > 5000) {
            $errors['body'] = 'Post body must not exceed 5,000 characters';
        }

        return $errors;
    }

    public function validateUpdate(UpdatePostDTO $dto)
    {
        $errors = [];

        if ($dto->id <= 0) {
            $errors['id'] = 'Invalid post ID';
        }

        if (empty($dto->title)) {
            $errors['title'] = 'Post title is required';
        } elseif (mb_strlen($dto->title) < 3 || mb_strlen($dto->title) > 100) {
            $errors['title'] = 'Title must be between 3 and 100 characters';
        }

        if (empty($dto->body)) {
            $errors['body'] = 'Post body is required';
        } elseif (mb_strlen($dto->body) > 5000) {
            $errors['body'] = 'Post body must not exceed 5,000 characters';
        }

        return $errors;
    }
}
