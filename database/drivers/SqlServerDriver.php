<?php

require_once __DIR__ . "/DatabaseDriverInterface.php";

class SqlServerDriver implements DatabaseDriverInterface
{
    private $connection = null;

    public function connect()
    {
        $configPath = __DIR__ . '/../config/database.json';

        if (!file_exists($configPath)) {
            throw new Exception("database.json not found.");
        }

        $config = json_decode(file_get_contents($configPath), true);

        if (!$config) {
            throw new Exception("Invalid database.json");
        }

        $server   = $config["server"];
        $database = $config["database"];
        $username = $config["username"];
        $password = $config["password"];

        // Driver (default to ODBC Driver 18 if not specified)
        $driver = ($config["driver"] === "auto")
            ? "ODBC Driver 18 for SQL Server"
            : $config["driver"];

        // Read options from JSON
        $encrypt = !empty($config["options"]["encrypt"]) ? "yes" : "no";
        $trust   = !empty($config["options"]["trustServerCertificate"]) ? "yes" : "no";

        // Build DSN
        $dsn = "Driver={$driver};"
             . "Server={$server};"
             . "Database={$database};"
             . "Encrypt={$encrypt};"
             . "TrustServerCertificate={$trust};";

        $this->connection = odbc_connect(
            $dsn,
            $username,
            $password
        );

        if (!$this->connection) {
            throw new Exception(odbc_errormsg());
        }

        return $this->connection;
    }

    public function disconnect()
    {
        if ($this->connection) {
            odbc_close($this->connection);
            $this->connection = null;
        }
    }

    public function query($sql)
    {
        return odbc_exec($this->connection, $sql);
    }

    public function execute($sql)
    {
        return $this->query($sql);
    }

    public function fetch($result)
    {
        return odbc_fetch_array($result);
    }

    public function beginTransaction()
    {
        odbc_autocommit($this->connection, false);
    }

    public function commit()
    {
        odbc_commit($this->connection);
        odbc_autocommit($this->connection, true);
    }

    public function rollback()
    {
        odbc_rollback($this->connection);
        odbc_autocommit($this->connection, true);
    }

    public function getConnection()
    {
        return $this->connection;
    }
}