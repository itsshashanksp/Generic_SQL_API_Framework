<?php

require_once __DIR__ . '/Database.php';

class QueryEngine
{
    private $db;

    private $connection;

    public function __construct()
    {
        $this->db = new Database();

        $this->connection = $this->db->getConnection();
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

    /**
     * Execute SQL
     */
    public function execute($sql)
    {
        $startTime = microtime(true);

        $result = odbc_exec($this->connection, $sql);

        if (!$result) {
            throw new Exception(odbc_errormsg($this->connection));
        }

        $rows = [];

        while ($row = odbc_fetch_array($result)) {
            $rows[] = $row;
        }

        return $this->buildResult($rows, $startTime);
    }

    /**
     * Execute Prepared SQL
     */
    public function executePrepared($sql, array $params = [])
    {
        $startTime = microtime(true);

        $statement = odbc_prepare($this->connection, $sql);

        if (!$statement) {
            throw new Exception(odbc_errormsg($this->connection));
        }

        $result = @odbc_execute($statement, $params);

        if ($result === false) {
            throw new Exception(odbc_errormsg($this->connection));
        }

        $rows = [];

        while ($row = odbc_fetch_array($statement)) {
            $rows[] = $row;
        }

        return $this->buildResult($rows, $startTime);
    }

        /**
     * Execute Prepared SQL With Result
     */
    public function executePreparedQuery($sql, array $params = [])
    {
        $startTime = microtime(true);

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

        return $this->buildResult($rows, $startTime);
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