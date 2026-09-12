<?php

require_once __DIR__ . "/../drivers/SqlServerDriver.php";
require_once __DIR__ . "/../../app/Security/DatabaseConfigurationResolver.php";

class DriverFactory
{
    public static function create()
    {
        $config = DatabaseConfigurationResolver::load(
            __DIR__ . "/../config/database.json"
        );

        switch (strtolower($config["provider"])) {

            case "sqlserver":
                return new SqlServerDriver($config);

            default:
                throw new Exception("Unsupported database provider.");
        }
    }
}
