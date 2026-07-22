<?php

require_once __DIR__ . '/Middleware.php';

class LoggingMiddleware extends Middleware
{
    public function handle(array $request): void
    {
        $logFile = __DIR__ . '/../../logs/api.log';

        $log = sprintf(
            "[%s] %s %s\n",
            date('Y-m-d H:i:s'),
            $_SERVER['REQUEST_METHOD'],
            json_encode($request)
        );

        file_put_contents($logFile, $log, FILE_APPEND);
    }
}
