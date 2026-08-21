<?php

require_once __DIR__ . '/../core/Database.php';

try {

    $database = new Database();

    echo "CONNECTED";

    $database->close();

    exit(0);

} catch (Throwable $e) {

    echo "FAILED: " . $e->getMessage();

    exit(1);
}