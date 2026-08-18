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

        $response = [
            "success" => true,
            "message" => $message
        ];

        if (
            is_array($data) &&
            isset($data["executionTime"]) &&
            isset($data["rowsReturned"]) &&
            isset($data["data"])
        ) {
            $response["executionTime"] = $data["executionTime"];
            $response["rowsReturned"]  = $data["rowsReturned"];
            if (isset($data["totalRows"])) {
                $response["totalRows"] = $data["totalRows"];
            }
            $response["data"]          = $data["data"];
        } else {
            $response["data"] = $data;
        }

        echo json_encode($response, JSON_PRETTY_PRINT);

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