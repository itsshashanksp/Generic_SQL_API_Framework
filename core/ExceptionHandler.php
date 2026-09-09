<?php

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../app/Requests/ApiRequestException.php';
require_once __DIR__ . '/../app/Security/DatabaseCredentialException.php';
require_once __DIR__ . '/QueryTimeoutException.php';

class ExceptionHandler
{
    public static function register(): void
    {
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error === null
                || $error['type'] !== E_ERROR
                || stripos($error['message'], 'Maximum execution time') === false) {
                return;
            }
            while (ob_get_level() > 0) ob_end_clean();
            (new Logger())->timing('request_error', defined('API_REQUEST_STARTED')
                ? (microtime(true) - API_REQUEST_STARTED) * 1000
                : 0, ['errorType' => 'PHPExecutionTimeout']);
            if (!headers_sent()) {
                http_response_code(504);
                header('Content-Type: application/json');
            }
            echo json_encode(
                Response::errorPayload('Query execution timed out.', 'QUERY_ERROR'),
                JSON_PRETTY_PRINT
            );
        });

        set_exception_handler(function (Throwable $exception) {

            $logger = new Logger();

            $logger->error(
                "Unhandled Exception",
                [],
                self::formatExceptionForLog($exception)
            );

            if ($exception instanceof ApiRequestException) {
                Response::error(
                    $exception->getMessage(),
                    $exception->getStatusCode(),
                    $exception->getErrorCode(),
                    $exception->getDetails()
                );
            }

            if ($exception instanceof QueryTimeoutException) {
                Response::error('Query execution timed out.', 504, 'QUERY_ERROR');
            }

            Response::error('Query execution failed.', 500, 'QUERY_ERROR');

        });
    }

    private static function formatExceptionForLog(Throwable $exception): string
    {
        if ($exception instanceof DatabaseCredentialException) {
            return $exception->getMessage();
        }

        return $exception->getMessage() . PHP_EOL . $exception->getTraceAsString();
    }
}
