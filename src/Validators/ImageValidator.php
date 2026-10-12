<?php

namespace App\Validators;

class ImageValidator
{
    private const MAX_SIZE = 5 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    public function validate(?array $file, string $fieldName = 'image'): array
    {
        $errors = [];

        if (!$file) {
            $errors[$fieldName] = 'No image file provided';
            return $errors;
        }

        if (
            !isset($file['error']) ||
            $file['error'] !== UPLOAD_ERR_OK
        ) {
            $errors[$fieldName] = 'File upload failed';
            return $errors;
        }

        if (
            !isset($file['tmp_name']) ||
            !is_uploaded_file($file['tmp_name'])
        ) {
            $errors[$fieldName] = 'Invalid uploaded file';
            return $errors;
        }

        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            $errors[$fieldName] = 'Image must not exceed 5 MB';
            return $errors;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array(
            $mimeType,
            self::ALLOWED_MIME_TYPES,
            true
        )) {
            $errors[$fieldName] = 'Only JPEG, PNG, and WEBP are allowed';
        }

        return $errors;
    }
}