<?php

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Logger.php';

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

            Response::error(
                $exception->getMessage(),
                500
            );

        });
    }
}