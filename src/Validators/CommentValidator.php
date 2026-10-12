<?php

namespace App\Validators;

use App\DTOs\Input\CreateCommentDTO;
use App\DTOs\Input\UpdateCommentDTO;

class CommentValidator
{
    public function validate(CreateCommentDTO $dto): array
    {
        $errors = [];

        if ($dto->postId <= 0) {
            $errors['postId'] = 'Invalid post ID';
        }

        if ($dto->authorId <= 0) {
            $errors['authorId'] = 'Invalid user ID';
        }

        if (empty($dto->body)) {
            $errors['body'] = 'Comment cannot be empty';
        } elseif (mb_strlen($dto->body) > 5000) {
            $errors['body'] = 'Comment must not exceed 5,000 characters';
        }

        if ($dto->parentId !== null && $dto->parentId <= 0) {
            $errors['parentId'] = 'Invalid parent comment ID';
        }

        return $errors;
    }

    public function validateUpdate(UpdateCommentDTO $dto): array
    {
        $errors = [];

        if ($dto->id <= 0) {
            $errors['id'] = 'Invalid comment ID';
        }

        if (empty($dto->body)) {
            $errors['body'] = 'Comment cannot be empty';
        } elseif (mb_strlen($dto->body) > 5000) {
            $errors['body'] = 'Comment must not exceed 5,000 characters';
        }

        return $errors;
    }
}