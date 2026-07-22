<?php

class Database
{
    private $connection;

    public function __construct()
    {
        require_once __DIR__ . '/../config/constants.php';

        $config = require __DIR__ . '/../config/database.php';

        $connectionString = trim(file_get_contents($config['connection_file']));
        $username         = trim(file_get_contents($config['username_file']));
        $password         = trim(file_get_contents($config['password_file']));

        $this->connection = odbc_connect(
            $connectionString,
            $username,
            $password
        );

        if (!$this->connection) {
            die("Database Connection Failed : " . odbc_errormsg());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function close()
    {
        if ($this->connection) {
            odbc_close($this->connection);
        }
    }
}
