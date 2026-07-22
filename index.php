<?php

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Response.php';

try {

    $database = new Database();

    $connection = $database->getConnection();

    if ($connection) {

        Response::success(
            [],
            "Database Connected Successfully"
        );

    } else {

        Response::error(
            "Unable to Connect Database"
        );

    }

} catch (Exception $e) {

    Response::error(
        $e->getMessage()
    );

}
