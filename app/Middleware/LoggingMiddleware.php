<?php

require_once __DIR__ . '/Middleware.php';
require_once __DIR__ . '/../../core/Logger.php';

class LoggingMiddleware extends Middleware
{
    public function handle(array $request): void
    {
        (new Logger())->timing('request_received', 0, [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'action' => $request['action'] ?? null,
            'resource' => $request['resource'] ?? null,
            'page' => $request['pagination']['page'] ?? null,
            'pageSize' => $request['pagination']['pageSize'] ?? null,
            'filterCount' => is_array($request['filters'] ?? null) ? count($request['filters']) : 0,
            'sortCount' => is_array($request['sort'] ?? null) ? count($request['sort']) : 0,
        ]);
    }
}
