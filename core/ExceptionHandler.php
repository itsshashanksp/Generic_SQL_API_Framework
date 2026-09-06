<?php

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../app/Requests/ApiRequestException.php';

class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler(function (Throwable $exception) {

            $logger = new Logger();

            $logger->error(
                "Unhandled Exception",
                [],
                $exception->getMessage() . PHP_EOL .
                $exception->getTraceAsString()
            );

            if ($exception instanceof ApiRequestException) {
                Response::error(
                    $exception->getMessage(),
                    $exception->getStatusCode(),
                    $exception->getErrorCode(),
                    $exception->getDetails()
                );
            }

            Response::error('Query execution failed.', 500, 'QUERY_ERROR');

        });
    }
}
