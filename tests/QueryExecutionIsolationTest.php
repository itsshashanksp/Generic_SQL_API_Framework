<?php

require_once __DIR__ . '/../core/QueryEngine.php';
require_once __DIR__ . '/../core/Response.php';

function executionAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

class FakeOdbcQueryEngine extends QueryEngine
{
    public array $executions = [];
    public array $freed = [];
    public array $configuredTimeouts = [];
    public ?string $failure = null;
    private array $rowsBySql;

    public function __construct(array $rowsBySql, Logger $logger, int $timeout = 7)
    {
        $this->rowsBySql = $rowsBySql;
        parent::__construct(null, $logger, $timeout, false);
    }

    protected function prepareStatement(string $sql)
    {
        $statement = new stdClass();
        $statement->sql = $sql;
        $statement->position = 0;
        $statement->rows = $this->rowsBySql[$sql] ?? [];
        return $statement;
    }

    protected function configureStatementTimeout($statement): void
    {
        $this->configuredTimeouts[] = $this->queryTimeoutSeconds;
    }

    protected function executeStatement($statement, array $params): bool
    {
        $this->executions[] = ['sql' => $statement->sql, 'params' => $params];
        return $this->failure === null;
    }

    protected function fetchRow($statement)
    {
        if ($statement->position >= count($statement->rows)) return false;
        return $statement->rows[$statement->position++];
    }

    protected function freeStatement($statement): void
    {
        $this->freed[] = $statement->sql;
    }

    protected function lastError(): string
    {
        return $this->failure ?? '';
    }
}

$logDirectory = sys_get_temp_dir() . '/generic-dashboard-query-tests-' . bin2hex(random_bytes(5));
$logger = new Logger($logDirectory);
$first = new FakeOdbcQueryEngine(['SELECT first' => [['id' => 1]]], $logger);
$second = new FakeOdbcQueryEngine(['SELECT second' => [['id' => 2]]], $logger);

$firstResult = $first->executePrepared('SELECT first', ['alpha'], ['queryPhase' => 'data']);
$secondResult = $second->executePrepared('SELECT second', ['beta'], ['queryPhase' => 'data']);
executionAssert($firstResult['data'] === [['id' => 1]], 'The first engine returned another execution\'s rows.');
executionAssert($secondResult['data'] === [['id' => 2]], 'The second engine returned another execution\'s rows.');
executionAssert($first->executions[0]['params'] === ['alpha'], 'Parameters leaked into the first execution.');
executionAssert($second->executions[0]['params'] === ['beta'], 'Parameters leaked into the second execution.');
executionAssert($first->configuredTimeouts === [7] && $second->configuredTimeouts === [7], 'Statement timeout was not independently configured.');
executionAssert($first->freed === ['SELECT first'] && $second->freed === ['SELECT second'], 'Successful statements were not released.');

$first->failure = '[Microsoft][ODBC Driver] Query timeout expired (HYT00)';
try {
    $first->executePrepared('SELECT first', ['secret-value'], ['queryPhase' => 'pagination_count']);
    throw new RuntimeException('A database timeout was accepted as success.');
} catch (QueryTimeoutException $exception) {
    executionAssert(str_contains($exception->getMessage(), '7-second'), 'Timeout message omitted the configured boundary.');
}
executionAssert(count($first->freed) === 2, 'A failed statement was not released.');

$first->failure = null;
$recovered = $first->executePrepared('SELECT first', ['fresh']);
executionAssert($recovered['rowsReturned'] === 1, 'A failed query corrupted the next execution.');
executionAssert($first->executions[2]['params'] === ['fresh'], 'Failed-query parameters leaked into the next execution.');

$payload = Response::errorPayload('Query execution timed out.', 'QUERY_ERROR');
executionAssert($payload['success'] === false && $payload['error']['code'] === 'QUERY_ERROR', 'Timeout response broke the API error contract.');

$logFile = $logDirectory . '/' . date('Y-m-d') . '.log';
$log = (string)file_get_contents($logFile);
executionAssert(str_contains($log, '"phase":"query_execute"'), 'Execution timing diagnostics were not written.');
executionAssert(str_contains($log, '"phase":"rows_fetch"'), 'Fetch timing diagnostics were not written.');
executionAssert(str_contains($log, '"queryPhase":"pagination_count"'), 'Pagination count phase was not identified.');
executionAssert(!str_contains($log, 'secret-value'), 'A sensitive parameter value was written to diagnostics.');

echo "Query execution isolation tests passed.\n";
