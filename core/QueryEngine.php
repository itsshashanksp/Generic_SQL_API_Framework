<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/QueryTimeoutException.php';

class QueryEngine
{
    protected ?Database $db = null;
    protected $connection = null;
    protected Logger $logger;
    protected int $queryTimeoutSeconds;

    public function __construct(?Database $database = null, ?Logger $logger = null, ?int $queryTimeoutSeconds = null, bool $connect = true)
    {
        $this->logger = $logger ?? new Logger();
        $settings = require __DIR__ . '/../config/performance.php';
        $this->queryTimeoutSeconds = $queryTimeoutSeconds ?? (int)$settings['database_query_timeout_seconds'];
        if ($connect) {
            $started = microtime(true);
            try {
                $this->db = $database ?? new Database();
                $this->connection = $this->db->getConnection();
                $this->logger->timing('database_connection', $this->elapsed($started), ['success' => true]);
            } catch (Throwable $exception) {
                $this->logger->timing('database_connection', $this->elapsed($started), [
                    'success' => false,
                    'errorType' => get_class($exception),
                ]);
                throw $exception;
            }
        }
    }

    public function __destruct() { $this->close(); }

    public function getQuery($file)
    {
        if (!file_exists($file)) throw new Exception('SQL File Not Found : ' . $file);
        return file_get_contents($file);
    }

    public function execute($sql, array $context = [])
    {
        return $this->runStatement((string)$sql, [], true, false, $context);
    }

    public function executePrepared($sql, array $params = [], array $context = [])
    {
        return $this->runStatement((string)$sql, $params, true, false, $context);
    }

    public function executePreparedQuery($sql, array $params = [], array $context = [])
    {
        return $this->runStatement((string)$sql, $params, true, true, $context);
    }

    private function runStatement(string $sql, array $params, bool $prepared, bool $consumeAllResults, array $context): array
    {
        $totalStarted = microtime(true);
        $statement = null;
        $phase = $prepared ? 'prepare' : 'execute';
        $logContext = $context + [
            'sql' => $this->logger->safeSql($sql),
            'parameters' => $this->logger->parameterMetadata($params),
        ];
        try {
            if ($prepared) {
                $started = microtime(true);
                $statement = $this->prepareStatement($sql);
                $this->logger->timing('query_prepare', $this->elapsed($started), $logContext);
                if (!$statement) throw new RuntimeException($this->lastError());
                $this->configureStatementTimeout($statement);
                $phase = 'execute';
                $started = microtime(true);
                $executed = $this->executeStatement($statement, $params);
                $this->logger->timing('query_execute', $this->elapsed($started), $logContext);
                if ($executed === false) throw new RuntimeException($this->lastError());
            } else {
                $started = microtime(true);
                $statement = $this->executeDirect($sql);
                $this->logger->timing('query_execute', $this->elapsed($started), $logContext);
                if (!$statement) throw new RuntimeException($this->lastError());
            }

            $phase = 'fetch';
            $started = microtime(true);
            $rows = [];
            do {
                while ($row = $this->fetchRow($statement)) $rows[] = $row;
            } while ($consumeAllResults && $this->nextResult($statement));
            $this->logger->timing('rows_fetch', $this->elapsed($started), $context + ['rowsReturned' => count($rows)]);
            $result = [
                'executionTime' => round($this->elapsed($totalStarted), 2),
                'rowsReturned' => count($rows),
                'data' => $rows,
            ];
            $this->logger->timing('query_total', $result['executionTime'], $context + ['rowsReturned' => $result['rowsReturned']]);
            return $result;
        } catch (Throwable $exception) {
            $converted = $this->isTimeout($exception)
                ? new QueryTimeoutException("Database query exceeded the configured {$this->queryTimeoutSeconds}-second timeout.", 0, $exception)
                : $exception;
            $this->logger->error($sql, $params, get_class($converted) . ': ' . $converted->getMessage(), $this->elapsed($totalStarted));
            $this->logger->timing('query_error', $this->elapsed($totalStarted), $context + [
                'queryPhase' => $phase,
                'errorType' => get_class($converted),
            ]);
            throw $converted;
        } finally {
            if ($statement) $this->freeStatement($statement);
        }
    }

    protected function prepareStatement(string $sql) { return @odbc_prepare($this->connection, $sql); }

    protected function configureStatementTimeout($statement): void
    {
        if ($this->queryTimeoutSeconds <= 0) return;
        // ODBC statement option 0 is SQL_QUERY_TIMEOUT; type 2 selects statement options.
        if (!odbc_setoption($statement, 2, 0, $this->queryTimeoutSeconds)) {
            throw new RuntimeException('The ODBC driver could not configure the statement execution limit.');
        }
    }

    protected function executeStatement($statement, array $params): bool { return @odbc_execute($statement, $params); }
    protected function executeDirect(string $sql) { return @odbc_exec($this->connection, $sql); }
    protected function fetchRow($statement) { return odbc_fetch_array($statement); }
    protected function nextResult($statement): bool { return @odbc_next_result($statement); }
    protected function freeStatement($statement): void { @odbc_free_result($statement); }
    protected function lastError(): string { return (string)odbc_errormsg($this->connection); }

    private function isTimeout(Throwable $exception): bool
    {
        return $exception instanceof QueryTimeoutException
            || preg_match('/(?:HYT00|HYT01|timeout|timed out|time limit)/i', $exception->getMessage()) === 1;
    }

    private function elapsed(float $started): float { return (microtime(true) - $started) * 1000; }

    public function executeFile($file)
    {
        return $this->execute($this->getQuery($file), ['queryPhase' => 'metadata']);
    }

    public function close(): void
    {
        if ($this->db !== null) {
            $this->db->close();
            $this->db = null;
            $this->connection = null;
        }
    }
}
