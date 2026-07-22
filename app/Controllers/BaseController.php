<?php

require_once __DIR__ . '/../../core/Response.php';

class BaseController
{
    protected function success($data = [], $message = "Success", $code = 200)
    {
        Response::success($data, $message, $code);
    }

    protected function error($message = "Something went wrong", $code = 500)
    {
        Response::error($message, $code);
    }
}
