<?php

class Response
{
    /**
     * Success Response
     */
    public static function success($data = [], $message = "Success", $code = 200)
    {
        http_response_code($code);

        header("Content-Type: application/json");

        echo json_encode([
            "success" => true,
            "message" => $message,
            "data" => $data
        ], JSON_PRETTY_PRINT);

        exit;
    }

    /**
     * Error Response
     */
    public static function error($message = "Something went wrong", $code = 500)
    {
        http_response_code($code);

        header("Content-Type: application/json");

        echo json_encode([
            "success" => false,
            "message" => $message
        ], JSON_PRETTY_PRINT);

        exit;
    }
}
