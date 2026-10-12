<?php

namespace App\Validators;

class AvatarValidator
{
    private const MAX_SIZE = 5 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    public function validate(?array $file): array
    {
        $errors = [];

        if (!$file) {
            $errors['avatar'] = 'No avatar file provided';
            return $errors;
        }

        if (
            !isset($file['error']) ||
            $file['error'] !== UPLOAD_ERR_OK
        ) {
            $errors['avatar'] = 'File upload failed';
            return $errors;
        }

        if (
            !isset($file['tmp_name']) ||
            !is_uploaded_file($file['tmp_name'])
        ) {
            $errors['avatar'] = 'Invalid uploaded file';
            return $errors;
        }

        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            $errors['avatar'] = 'Avatar must not exceed 5 MB';
            return $errors;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $errors['avatar'] = 'Only JPEG, PNG, and WEBP are allowed';
        }

        return $errors;
    }
}
