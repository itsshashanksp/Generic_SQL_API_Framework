<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

class QueryEngine
{
    private $db;

    private $connection;
    private Logger $logger;

    public function __construct()
    {
        $this->db = new Database();

        $this->connection = $this->db->getConnection();
        $this->logger = new Logger();
    }

    /**
     * Read SQL File
     */
    public function getQuery($file)
    {
        if (!file_exists($file)) {
            throw new Exception("SQL File Not Found : " . $file);
        }

        return file_get_contents($file);
    }

    /**
     * Build Query Result
     */
    private function buildResult(array $rows, float $startTime): array
    {
        return [
            "executionTime" => round((microtime(true) - $startTime) * 1000, 2),
            "rowsReturned"  => count($rows),
            "data"          => $rows
        ];
    }

    /*
    * Log Query Success
    */
        private function logSuccess(
        string $sql,
        array $params,
        array $result
    ): void {

        $message =
            "========================================\n";

        $message .=
            "Date : "
            . date("Y-m-d H:i:s")
            . "\n";

        $message .=
            "Status : SUCCESS\n";

        $message .=
            "Execution Time : "
            . $result["executionTime"]
            . " ms\n";

        $message .=
            "Rows Returned : "
            . $result["rowsReturned"]
            . "\n\n";

        $message .=
            "SQL:\n"
            . $sql
            . "\n\n";

        $message .=
            "Parameters:\n"
            . json_encode(
                $params,
                JSON_PRETTY_PRINT
            )
            . "\n";

        $message .=
            "========================================\n\n";

        $this->logger->write($message);
    }

/**
 * Execute SQL
 */
public function execute($sql)
{
    $startTime = microtime(true);

    try {

        $query = odbc_exec($this->connection, $sql);

        if (!$query) {
            throw new Exception(odbc_errormsg($this->connection));
        }

        $rows = [];

        while ($row = odbc_fetch_array($query)) {
            $rows[] = $row;
        }

        $result = $this->buildResult($rows, $startTime);

        $this->logSuccess(
            $sql,
            [],
            $result
        );

        return $result;

    } catch (Exception $e) {

        $this->logger->error(
            $sql,
            [],
            $e->getMessage(),
            (microtime(true) - $startTime) * 1000
        );

        throw $e;
    }
}

/**
 * Execute Prepared SQL
 */
public function executePrepared($sql, array $params = [])
{
    $startTime = microtime(true);

    try {

        $statement = odbc_prepare($this->connection, $sql);

        if (!$statement) {
            throw new Exception(odbc_errormsg($this->connection));
        }

        $executed = @odbc_execute($statement, $params);

        if ($executed === false) {
            throw new Exception(odbc_errormsg($this->connection));
        }

        $rows = [];

        while ($row = odbc_fetch_array($statement)) {
            $rows[] = $row;
        }

        $result = $this->buildResult($rows, $startTime);

        $this->logSuccess(
            $sql,
            $params,
            $result
        );

        return $result;

    } catch (Exception $e) {

        $this->logger->error(
            $sql,
            $params,
            $e->getMessage(),
            (microtime(true) - $startTime) * 1000
        );

        throw $e;
    }
}

/**
 * Execute Prepared SQL With Result
 */
public function executePreparedQuery($sql, array $params = [])
{
    $startTime = microtime(true);

    try {

        $statement = odbc_prepare($this->connection, $sql);

        if (!$statement) {
            throw new Exception(odbc_errormsg($this->connection));
        }

        if (!@odbc_execute($statement, $params)) {
            throw new Exception(odbc_errormsg($this->connection));
        }

        $rows = [];

        do {

            while ($row = odbc_fetch_array($statement)) {
                $rows[] = $row;
            }

        } while (@odbc_next_result($statement));

        $result = $this->buildResult($rows, $startTime);

        $this->logSuccess(
            $sql,
            $params,
            $result
        );

        return $result;

    } catch (Exception $e) {

        $this->logger->error(
            $sql,
            $params,
            $e->getMessage(),
            (microtime(true) - $startTime) * 1000
        );

        throw $e;
    }
}

    /**
     * Execute SQL File
     */
    public function executeFile($file)
    {
        $sql = $this->getQuery($file);

        return $this->execute($sql);
    }

    /**
     * Close Database
     */
    public function close()
    {
        $this->db->close();
    }
}     