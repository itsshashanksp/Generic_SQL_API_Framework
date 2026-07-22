<?php

require_once __DIR__ . '/Response.php';

class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler(function (Throwable $exception) {

            $logFile = __DIR__ . '/../logs/error.log';

            $message = sprintf(
                "[%s] %s\n%s\n\n",
                date('Y-m-d H:i:s'),
                $exception->getMessage(),
                $exception->getTraceAsString()
            );

            file_put_contents(
                $logFile,
                $message,
                FILE_APPEND
            );

            Response::error(
                $exception->getMessage(),
                500
            );

        });
    }
}