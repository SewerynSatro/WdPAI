<?php

if ((getenv('APP_ENV') ?: 'production') !== 'development') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);

    set_exception_handler(function (Throwable $exception): void {
        error_log('Unhandled exception: ' . get_class($exception));
        http_response_code(500);
        include 'public/views/500.html';
        exit();
    });

    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        error_log('Unhandled PHP error.');
        http_response_code(500);
        include 'public/views/500.html';
        exit();
    });
}

require_once 'Routing.php';

$path = trim($_SERVER['REQUEST_URI'], '/');
$path = parse_url($path, PHP_URL_PATH);
Routing::run($path);


