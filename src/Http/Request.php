<?php

namespace App\Http;

class Request
{
    public string $method;
    public string $uri;
    public array $headers;
    public array $params;
    public ?array $body;
    public ?object $user = null;
    public ?string $sessionFamilyId = null;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->headers = getallheaders();
        $this->params = $_GET;
        $this->body = json_decode(file_get_contents('php://input'), true);
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->headers['Authorization'] ?? $this->headers['authorization'] ?? '';
        return preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $matches) ? $matches[1] : null;
    }
}
