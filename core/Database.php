<?php

require_once __DIR__ . "/../database/factory/DriverFactory.php";

class Database
{
    private $driver;

    public function __construct()
    {
        $this->driver = DriverFactory::create();
        $this->driver->connect();
    }

    public function getConnection()
    {
        return $this->driver->getConnection();
    }

    public function close()
    {
        $this->driver->disconnect();
    }
}
