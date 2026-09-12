<?php

require_once __DIR__ . "/DatabaseDriverInterface.php";
require_once __DIR__ . "/../../app/Security/DatabaseCredentialResolver.php";
require_once __DIR__ . "/../../app/Security/DatabaseConfigurationResolver.php";

class SqlServerDriver implements DatabaseDriverInterface
{
    private $connection = null;
    private ?array $configuration;

    public function __construct(?array $configuration = null)
    {
        $this->configuration = $configuration;
    }

    /**
     * SQL Server ODBC drivers.
     *
     * Drivers are tested from newest to oldest.
     */
    private function getSupportedDrivers(): array
    {
        return [
            // Modern Microsoft ODBC Drivers
            "ODBC Driver 19 for SQL Server",
            "ODBC Driver 18 for SQL Server",
            "ODBC Driver 17 for SQL Server",
            "ODBC Driver 13.1 for SQL Server",
            "ODBC Driver 13 for SQL Server",
            "ODBC Driver 11 for SQL Server",
            "ODBC Driver 10 for SQL Server",

            // SQL Server Native Client
            "SQL Server Native Client 11.0",
            "SQL Server Native Client 10.0",
            "SQL Server Native Client 9.0",

            // Legacy SQL Native Client
            "SQL Native Client",

            // Legacy SQL Server ODBC driver
            "SQL Server"
        ];
    }

    /**
     * Build SQL Server address.
     */
    private function buildServerAddress(
        string $server,
        $port
    ): string {

        $serverAddress = $server;

        if (
            !empty($port)
            && strpos($server, ",") === false
        ) {
            $serverAddress .= "," . $port;
        }

        return $serverAddress;
    }

    /**
     * Build ODBC connection string.
     */
    private function buildDsn(
        string $driver,
        string $serverAddress,
        string $database,
        string $authentication,
        string $encrypt,
        string $trust
    ): string {

        $dsn =
            "Driver={" . $driver . "};"
            . "Server={$serverAddress};"
            . "Database={$database};"
            . "Encrypt={$encrypt};"
            . "TrustServerCertificate={$trust};";

        /*
         * Windows Authentication
         */
        if ($authentication === "windows") {

            $dsn .=
                "Trusted_Connection=yes;";
        }

        return $dsn;
    }

    /**
     * Connect using authentication mode.
     */
    private function openConnection(
        string $dsn,
        string $authentication,
        string $username,
        string $password
    ) {

        /*
         * Windows Authentication
         */
        if ($authentication === "windows") {

            return @odbc_connect(
                $dsn,
                "",
                "",
                SQL_CUR_USE_ODBC
            );
        }

        /*
         * SQL Server Authentication
         */
        return @odbc_connect(
            $dsn,
            $username,
            $password,
            SQL_CUR_USE_ODBC
        );
    }

    /**
     * Automatically detect a working SQL Server driver.
     *
     * IMPORTANT:
     * The successful connection is kept open.
     */
    private function detectDriver(
        string $server,
        $port,
        string $database,
        string $username,
        string $password,
        string $authentication,
        string $encrypt,
        string $trust
    ): array {

        $drivers =
            $this->getSupportedDrivers();

        $serverAddress =
            $this->buildServerAddress(
                $server,
                $port
            );

        foreach ($drivers as $driver) {

            $dsn =
                $this->buildDsn(
                    $driver,
                    $serverAddress,
                    $database,
                    $authentication,
                    $encrypt,
                    $trust
                );

            $connection =
                $this->openConnection(
                    $dsn,
                    $authentication,
                    $username,
                    $password
                );

            /*
             * Driver + database connection succeeded.
             *
             * DO NOT CLOSE THIS CONNECTION.
             */
            if ($connection) {

                return [
                    "driver" => $driver,
                    "connection" => $connection
                ];
            }

        }

        throw new Exception('No compatible SQL Server ODBC driver could establish a connection.');
    }

    /**
     * Connect to SQL Server.
     */
    public function connect()
    {
        $configPath =
            __DIR__ . '/../config/database.json';

        $config = $this->configuration
            ?? DatabaseConfigurationResolver::load($configPath);

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
            DatabaseCredentialResolver::resolve(
                $config["password"] ?? ""
            );

        $authentication = strtolower(trim((string)($config["authentication"] ?? "sql")));

        /*
         * Validate authentication mode.
         */
        $allowedAuthenticationModes = [
            "sql",
            "windows"
        ];

        if (
            !in_array(
                $authentication,
                $allowedAuthenticationModes,
                true
            )
        ) {

            throw new Exception('Unsupported database authentication type.');
        }

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
         * Connection options.
         */
        $encrypt =
            !empty(
                $config["options"]["encrypt"]
            )
                ? "yes"
                : "no";

        $trust =
            !empty(
                $config["options"]
                    ["trustServerCertificate"]
            )
                ? "yes"
                : "no";

        /*
         * Driver configuration.
         */
        $configuredDriver = trim((string)($config["driver"] ?? "auto"));

        /*
         * AUTO DRIVER
         */
        if (
            strtolower(
                $configuredDriver
            ) === "auto"
        ) {

            $detected =
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

            /*
             * Keep the successful connection.
             */
            $this->connection =
                $detected["connection"];

            return $this->connection;
        }

        /*
         * MANUAL DRIVER
         */
        $driver =
            $configuredDriver;

        $serverAddress =
            $this->buildServerAddress(
                $server,
                $port
            );

        $dsn =
            $this->buildDsn(
                $driver,
                $serverAddress,
                $database,
                $authentication,
                $encrypt,
                $trust
            );

        $this->connection =
            $this->openConnection(
                $dsn,
                $authentication,
                $username,
                $password
            );

        if (!$this->connection) {

            throw new Exception('SQL Server connection failed.');
        }

        return $this->connection;
    }

    /**
     * Disconnect from SQL Server.
     */
    public function disconnect()
    {
        if ($this->connection) {

            @odbc_close(
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
        if (!$this->connection) {

            throw new Exception(
                "Database connection is not available."
            );
        }

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
     * Get active database connection.
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
