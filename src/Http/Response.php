<?php

namespace App\Http;

class Response
{
    public static function json($data, int $code = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success($payload, int $code = 200): void
    {
        $message = null;
        $meta = null;
        $data = $payload;

        if (is_array($payload) && !array_is_list($payload)) {
            $fields = $payload;
            if (array_key_exists('message', $fields) && is_string($fields['message'])) {
                $message = $fields['message'];
                unset($fields['message']);
            }

            $pagination = [];
            foreach (['count', 'page', 'limit', 'total', 'totalPages', 'total_pages'] as $key) {
                if (array_key_exists($key, $fields) && (is_int($fields[$key]) || is_float($fields[$key]))) {
                    $pagination[$key] = $fields[$key];
                    unset($fields[$key]);
                }
            }
            if ($pagination !== []) {
                $meta = $pagination;
            }

            if (count($fields) === 1 && array_key_exists('data', $fields)) {
                $data = $fields['data'];
            } else {
                $data = $fields === [] ? (object)[] : $fields;
            }
        }

        self::json(['data' => $data, 'meta' => $meta, 'message' => $message], $code);
    }

    public static function error(
        string $message,
        int $code = 400,
        ?string $errorCode = null,
        ?array $fields = null
    ): void {
        if ($code >= 500) {
            error_log($message);
            $message = 'Internal server error';
        }

        $error = [
            'message' => $message,
            'status' => $code,
            'code' => $errorCode ?? self::getErrorCode($code)
        ];

        if ($fields !== null && !empty($fields)) {
            $error['fields'] = $fields;
        }

        self::json([
            'error' => $error
        ], $code);
    }

    public static function validationError(array $errors): void
    {
        self::error(
            'Validation failed',
            422,
            'VALIDATION_ERROR',
            $errors
        );
    }

    private static function getErrorCode(int $code): string
    {
        return match ($code) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'UNPROCESSABLE_ENTITY',
            500 => 'INTERNAL_SERVER_ERROR',
            default => 'HTTP_ERROR'
        };
    }
}
