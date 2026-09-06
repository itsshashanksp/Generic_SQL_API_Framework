<?php

class Response
{
    private static array $requestContext = [];

    public static function setRequestContext(array $request): void
    {
        self::$requestContext = $request;
    }

    public static function successPayload($data = [], string $message = 'Success'): array
    {
        $rows = is_array($data) && isset($data['data']) && is_array($data['data'])
            ? $data['data']
            : (is_array($data) ? $data : []);
        $pagination = self::$requestContext['pagination'] ?? [];
        $rowsReturned = is_array($data) && isset($data['rowsReturned'])
            ? (int)$data['rowsReturned']
            : count($rows);

        return [
            'success' => true,
            'message' => $message,
            'data' => $rows,
            'meta' => [
                'page' => $pagination['page'] ?? null,
                'pageSize' => $pagination['pageSize'] ?? null,
                'totalRows' => is_array($data) && isset($data['totalRows'])
                    ? (int)$data['totalRows']
                    : $rowsReturned,
                'rowsReturned' => $rowsReturned,
                'executionTime' => is_array($data) && isset($data['executionTime'])
                    ? $data['executionTime']
                    : null
            ]
        ];
    }

    public static function errorPayload(
        string $message,
        string $errorCode = 'INTERNAL_ERROR',
        array $details = []
    ): array {
        return [
            'success' => false,
            'message' => $message,
            'error' => ['code' => $errorCode, 'details' => $details],
            'data' => []
        ];
    }

    public static function success($data = [], $message = 'Success', $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(self::successPayload($data, (string)$message), JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(
        $message = 'Something went wrong',
        $code = 500,
        string $errorCode = 'INTERNAL_ERROR',
        array $details = []
    ) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(
            self::errorPayload((string)$message, $errorCode, $details),
            JSON_PRETTY_PRINT
        );
        exit;
    }
}
