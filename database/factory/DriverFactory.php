<?php

require_once __DIR__ . "/../drivers/SqlServerDriver.php";

class DriverFactory
{
    public static function create()
    {
        $config = json_decode(
            file_get_contents(__DIR__ . "/../config/database.json"),
            true
        );

        switch (strtolower($config["provider"])) {

            case "sqlserver":
                return new SqlServerDriver();

            default:
                throw new Exception("Unsupported database provider.");
        }
    }
}