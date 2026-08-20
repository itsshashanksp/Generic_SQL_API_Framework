<?php

require_once __DIR__ . "/DatabaseDriverInterface.php";

class SqlServerDriver implements DatabaseDriverInterface
{
    private $connection = null;

    /**
     * Detect a compatible SQL Server ODBC driver.
     *
     * Drivers are tested from newest to oldest.
     */
    private function detectDriver(
    $server,
    $port,
    $database,
    $username,
    $password,
    $authentication,
    $encrypt,
    $trust
) {
    $drivers = [
        "ODBC Driver 19 for SQL Server",
        "ODBC Driver 18 for SQL Server",
        "ODBC Driver 17 for SQL Server",
        "ODBC Driver 13.1 for SQL Server",
        "ODBC Driver 13 for SQL Server",
        "ODBC Driver 11 for SQL Server",
        "ODBC Driver 10 for SQL Server",

        "SQL Server Native Client 11.0",
        "SQL Server Native Client 10.0",
        "SQL Server Native Client 9.0",

        "SQL Native Client",
        "SQL Server"
    ];

    /*
     * Build server address.
     */
    $serverAddress = $server;

    if (!empty($port)) {
        $serverAddress .= "," . $port;
    }

    $errors = [];

    foreach ($drivers as $driver) {

        $dsn =
            "Driver={" . $driver . "};"
            . "Server={$serverAddress};"
            . "Database={$database};"
            . "Encrypt={$encrypt};"
            . "TrustServerCertificate={$trust};";

    if (
        strtolower(trim($authentication)) === "windows"
    ) {
        $dsn .= "Trusted_Connection=yes;";

        $connection = @odbc_connect(
            $dsn,
            "",
            "",
            SQL_CUR_USE_ODBC
        );
    } else {        
        $connection = @odbc_connect(
            $dsn,
            $username,
            $password,
            SQL_CUR_USE_ODBC
        );
    }

        if ($connection) {

            odbc_close($connection);

            return $driver;
        }

        $error = odbc_errormsg();

        $errors[] =
            $driver . ": " . $error;
    }

    throw new Exception(
        "No compatible SQL Server ODBC driver could establish a connection."
        . "\n\nServer: " . $serverAddress
        . "\nDatabase: " . $database
        . "\n\nDriver errors:\n"
        . implode("\n", $errors)
    );
}

    /**
     * Connect to SQL Server.
     */
    public function connect()
    {
        $configPath =
            __DIR__ . '/../config/database.json';

        if (!file_exists($configPath)) {
            throw new Exception(
                "database.json not found."
            );
        }

        $config = json_decode(
            file_get_contents($configPath),
            true
        );

        if (!is_array($config)) {
            throw new Exception(
                "Invalid database.json"
            );
        }

        /*
         * Database configuration.
         */
        $server =
            $config["server"] ?? null;

        $port =
            $config["port"] ?? null;

        $database =
            $config["database"] ?? null;

        $username =
            $config["username"] ?? "";

        $password =
            $config["password"] ?? "";
        
        $authentication =
        strtolower(
            trim(
                $config["authentication"] ?? "sql"
            )
        );

        if (empty($server)) {
            throw new Exception(
                "Database server is not configured."
            );
        }

        if (empty($database)) {
            throw new Exception(
                "Database name is not configured."
            );
        }

        /*
         * Read connection options.
         */
        $encrypt =
            !empty(
                $config["options"]["encrypt"]
            )
                ? "yes"
                : "no";

        $trust =
            !empty(
                $config["options"]["trustServerCertificate"]
            )
                ? "yes"
                : "no";

        /*
         * Driver selection.
         */
        $configuredDriver =
            $config["driver"] ?? "auto";

        if (
            strtolower(
                trim($configuredDriver)
            ) === "auto"
        ) {

            $driver =
                $this->detectDriver(
                    $server,
                    $port,
                    $database,
                    $username,
                    $password,
                    $authentication,
                    $encrypt,
                    $trust
                );

        } else {

            $driver =
                trim($configuredDriver);
        }

        /*
         * Build server address.
         */
        $serverAddress =
            $server;

        if (!empty($port)) {
            $serverAddress .= "," . $port;
        }

        /*
         * Build ODBC connection string.
         */
        $dsn =
            "Driver={" . $driver . "};"
            . "Server={$serverAddress};"
            . "Database={$database};"
            . "Encrypt={$encrypt};"
            . "TrustServerCertificate={$trust};";

        /*
         * Establish connection.
         */
        if ($authentication === "windows") {

        $dsn .= "Trusted_Connection=yes;";

        $this->connection =
            @odbc_connect(
                $dsn,
                "",
                "",
                SQL_CUR_USE_ODBC
            );

    } else {
        $this->connection =
            @odbc_connect(
                $dsn,
                $username,
                $password,
                SQL_CUR_USE_ODBC
            );
    }

        if (!$this->connection) {

            throw new Exception(
                "SQL Server connection failed using "
                . "ODBC driver '{$driver}': "
                . odbc_errormsg()
            );
        }

        return $this->connection;
    }

    /**
     * Disconnect from SQL Server.
     */
    public function disconnect()
    {
        if ($this->connection) {

            odbc_close(
                $this->connection
            );

            $this->connection = null;
        }
    }

    /**
     * Execute SQL query.
     */
    public function query($sql)
    {
        return odbc_exec(
            $this->connection,
            $sql
        );
    }

    /**
     * Execute SQL.
     */
    public function execute($sql)
    {
        return $this->query($sql);
    }

    /**
     * Fetch a row.
     */
    public function fetch($result)
    {
        return odbc_fetch_array(
            $result
        );
    }

    /**
     * Begin transaction.
     */
    public function beginTransaction()
    {
        odbc_autocommit(
            $this->connection,
            false
        );
    }

    /**
     * Commit transaction.
     */
    public function commit()
    {
        odbc_commit(
            $this->connection
        );

        odbc_autocommit(
            $this->connection,
            true
        );
    }

    /**
     * Rollback transaction.
     */
    public function rollback()
    {
        odbc_rollback(
            $this->connection
        );

        odbc_autocommit(
            $this->connection,
            true
        );
    }

    /**
     * Get active connection.
     */
    public function getConnection()
    {
        return $this->connection;
    }
}