<?php

require_once __DIR__ . '/vendor/autoload.php';


if (file_exists(__DIR__ . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}



header("Content-Type: application/json; charset=utf-8");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit();
}

use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;


try {
    $request = new Request();
    $routes = require_once __DIR__ . '/src/router.php';

    $foundRoute = null;
    $routeParams = [];


    $currentMethod = strtoupper($request->method);

    foreach ($routes as $route) {

        if (strtoupper($route['method']) === $currentMethod && preg_match($route['pattern'], $request->uri, $matches)) {
            $foundRoute = $route;
            array_shift($matches);
            $routeParams = $matches;
            break;
        }
    }

    if (!$foundRoute) {
        Response::error("Not Found", 404);
        exit();
    }

    $controllerClass = $foundRoute['controller'];
    $action = $foundRoute['action'];
    $requiredRole = $foundRoute['auth'] ?? null;

    $authMiddleware = new AuthMiddleware();
    $authMiddleware->handle($request, $requiredRole);

    if (!class_exists($controllerClass)) {
        Response::error("Controller not found", 500);
        exit();
    }

    $controller = new $controllerClass();

    if (!method_exists($controller, $action)) {
        Response::error("Action not found in controller", 500);
        exit();
    }

    $controller->$action($request, ...$routeParams);
} catch (\Throwable $e) {
    error_log("ForumX unhandled exception: " . $e);
    Response::error("Internal Server Error", 500);
}
