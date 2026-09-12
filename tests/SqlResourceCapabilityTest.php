<?php

require_once __DIR__ . '/../app/Requests/QueryRequestValidator.php';
require_once __DIR__ . '/../app/Requests/QueryRequestNormalizer.php';
require_once __DIR__ . '/../app/Repositories/SqlRepository.php';

function resourceCapabilityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function resourceCapabilityFailure(callable $operation, string $message): Throwable
{
    try {
        $operation();
    } catch (Throwable $exception) {
        return $exception;
    }
    throw new RuntimeException($message);
}

class SqlResourceCapabilityEngine extends QueryEngine
{
    public array $executions = [];
    private int $compatibilityLevel;

    public function __construct(int $compatibilityLevel = 150)
    {
        $this->compatibilityLevel = $compatibilityLevel;
    }

    public function executePrepared($sql, array $params = [], array $context = [])
    {
        $this->executions[] = compact('sql', 'params', 'context');
        if (str_contains($sql, 'COUNT(*) AS TotalRows')) {
            return ['data' => [['TotalRows' => 3]]];
        }
        if (str_contains($sql, 'compatibility_level')) {
            return ['data' => [['CompatibilityLevel' => $this->compatibilityLevel]]];
        }
        return ['executionTime' => 0.1, 'rowsReturned' => 1, 'data' => [['ResultValue' => 1]]];
    }
}

$testDirectory = QUERY_PATH . DIRECTORY_SEPARATOR . 'sql-resource-capability-test-' . bin2hex(random_bytes(6));
$files = [];
$capabilities = [
    'cte' => "WITH Recent AS (SELECT Id, CreatedAt FROM dbo.Events WHERE Active = 1)\nSELECT Id AS ResultValue FROM Recent",
    'recursive-cte' => "WITH NumberTree AS (SELECT 1 AS N UNION ALL SELECT N + 1 FROM NumberTree WHERE N < 5)\nSELECT N AS ResultValue FROM NumberTree OPTION (MAXRECURSION 100)",
    'cte-top' => "WITH Ranked AS (SELECT Id FROM dbo.Events)\nSELECT TOP (10) Id AS ResultValue FROM Ranked ORDER BY Id DESC",
    'subqueries' => "SELECT Nested.Id AS ResultValue FROM (SELECT Id FROM dbo.Parent WHERE Id IN (SELECT ParentId FROM dbo.Child)) AS Nested",
    'case' => "SELECT CASE WHEN Status = 'A' THEN 1 WHEN Status = 'P' THEN 2 ELSE 0 END AS ResultValue FROM dbo.Customer",
    'complex-joins' => "SELECT A.Id AS ResultValue FROM dbo.A AS A FULL JOIN dbo.B AS B ON A.Id = B.Id AND B.Active = 1 CROSS JOIN dbo.C AS C CROSS APPLY dbo.SplitCodes(A.Code) AS S OUTER APPLY (SELECT TOP (1) X.Id FROM dbo.X AS X WHERE X.AId = A.Id) AS O",
    'window' => "SELECT ROW_NUMBER() OVER (PARTITION BY CustomerCode ORDER BY CreatedAt DESC) AS ResultValue FROM dbo.CustomerEvent",
    'sql-server-functions' => "SELECT DATEDIFF(day, CreatedAt, SYSUTCDATETIME()) + ABS(CHECKSUM(NEWID())) AS ResultValue FROM dbo.Events",
    'json-xml' => "SELECT COALESCE(JSON_VALUE(Data, '$.customer.name'), XmlData.value('(/customer/name/text())[1]', 'nvarchar(100)')) AS ResultValue FROM dbo.Documents",
    'union' => "SELECT Id AS ResultValue FROM dbo.CurrentRows UNION SELECT Id FROM dbo.ArchiveRows INTERSECT SELECT Id FROM dbo.AllowedRows EXCEPT SELECT Id FROM dbo.BlockedRows",
    'union-all' => "SELECT Id AS ResultValue FROM dbo.CurrentRows UNION ALL SELECT Id FROM dbo.ArchiveRows",
    'exists' => "SELECT P.Id AS ResultValue FROM dbo.Parent AS P WHERE EXISTS (SELECT 1 FROM dbo.Child AS C WHERE C.ParentId = P.Id) AND NOT EXISTS (SELECT 1 FROM dbo.Blocked AS B WHERE B.ParentId = P.Id)",
    'aggregate-having' => "SELECT COUNT_BIG(*) AS ResultValue FROM dbo.Sales GROUP BY CustomerCode HAVING SUM(Amount) > AVG(Amount)",
    'nested-functions' => "SELECT CONVERT(int, ROUND(ABS(COALESCE(dbo.ScoreFor(Id), 0.0)), 0)) AS ResultValue FROM dbo.Customer",
    'table-valued-function' => "SELECT F.Value AS ResultValue FROM dbo.ValuesForDate(CONVERT(date, GETDATE())) AS F",
];

try {
    mkdir($testDirectory, 0700, true);
    foreach ($capabilities as $id => $sql) {
        $file = $testDirectory . DIRECTORY_SEPARATOR . $id . '.sql';
        file_put_contents($file, $sql . ';' . PHP_EOL);
        $files[] = $file;
    }

    $registry = new SqlResourceRegistry([], $testDirectory);
    $resultExecution = ['execution' => [
        'columns' => ['ResultValue'],
        'defaultSort' => [['field' => 'ResultValue', 'direction' => 'ASC']],
    ]];
    foreach ($capabilities as $id => $authoredSql) {
        $engine = new SqlResourceCapabilityEngine();
        (new SqlRepository($engine, $registry))->execute(['resource' => $id]);
        $execution = end($engine->executions);
        $representativeFragment = match ($id) {
            'cte' => 'WITH Recent AS',
            'recursive-cte' => 'WITH NumberTree AS',
            'cte-top' => 'SELECT TOP (10)',
            'subqueries' => 'IN (SELECT ParentId',
            'case' => 'CASE WHEN Status',
            'complex-joins' => 'FULL JOIN dbo.B',
            'window' => 'PARTITION BY CustomerCode',
            'sql-server-functions' => 'SYSUTCDATETIME()',
            'json-xml' => "JSON_VALUE(Data, '$.customer.name')",
            'union' => ' INTERSECT ',
            'union-all' => ' UNION ALL ',
            'exists' => 'NOT EXISTS',
            'aggregate-having' => 'HAVING SUM(Amount)',
            'nested-functions' => 'dbo.ScoreFor(Id)',
            'table-valued-function' => 'dbo.ValuesForDate',
        };
        resourceCapabilityAssert(
            str_contains($execution['sql'], $representativeFragment),
            "Server-owned {$id} SQL did not reach generation unchanged."
        );
        if ($id === 'recursive-cte') {
            resourceCapabilityAssert(
                str_ends_with(trim($execution['sql']), 'OPTION (MAXRECURSION 100)'),
                'Recursive CTE query hint was moved inside a generated wrapper.'
            );
        }
    }

    $cteEngine = new SqlResourceCapabilityEngine();
    $injectionValue = "' OR 1=1 --";
    (new SqlRepository($cteEngine, $registry))->execute([
        'resource' => 'cte',
        ...$resultExecution,
        'filters' => [['field' => 'ResultValue', 'operator' => '=', 'value' => $injectionValue]],
        'pagination' => ['page' => 2, 'pageSize' => 10],
    ]);
    $cteCount = array_values(array_filter(
        $cteEngine->executions,
        fn (array $execution): bool => str_contains($execution['sql'], 'COUNT(*) AS TotalRows')
    ))[0];
    $cteData = end($cteEngine->executions);
    resourceCapabilityAssert(str_starts_with(trim($cteCount['sql']), 'WITH Recent AS'), 'CTE count lost its WITH scope.');
    resourceCapabilityAssert(
        strpos($cteCount['sql'], 'WITH Recent AS') < strpos($cteCount['sql'], 'SELECT COUNT(*)'),
        'CTE was incorrectly placed inside the count derived table.'
    );
    resourceCapabilityAssert(str_starts_with(trim($cteData['sql']), 'WITH Recent AS'), 'CTE data query lost its WITH scope.');
    resourceCapabilityAssert(str_contains($cteData['sql'], 'OFFSET 10 ROWS'), 'CTE pagination was not generated.');
    resourceCapabilityAssert($cteData['params'] === [$injectionValue], 'Injection-style filter value was not bound.');
    resourceCapabilityAssert(!str_contains($cteData['sql'], $injectionValue), 'Filter value became SQL syntax.');

    $legacyCteEngine = new SqlResourceCapabilityEngine(100);
    (new SqlRepository($legacyCteEngine, $registry))->execute([
        'resource' => 'cte',
        ...$resultExecution,
        'pagination' => ['page' => 1, 'pageSize' => 10],
    ]);
    $legacyCteData = end($legacyCteEngine->executions);
    resourceCapabilityAssert(
        str_starts_with(trim($legacyCteData['sql']), 'WITH Recent AS')
            && substr_count($legacyCteData['sql'], 'WITH Recent AS') === 1
            && str_contains($legacyCteData['sql'], 'ROW_NUMBER() OVER'),
        'Legacy SQL Server pagination did not preserve one valid CTE prefix.'
    );

    $cteTopEngine = new SqlResourceCapabilityEngine();
    $cteTopResult = (new SqlRepository($cteTopEngine, $registry))->execute([
        'resource' => 'cte-top',
        'pagination' => ['page' => 1, 'pageSize' => 10],
    ]);
    $cteTopData = end($cteTopEngine->executions);
    resourceCapabilityAssert(
        count($cteTopEngine->executions) === 1
            && str_starts_with(trim($cteTopData['sql']), 'WITH Ranked AS')
            && !str_contains($cteTopData['sql'], 'OFFSET 0 ROWS')
            && $cteTopResult['totalRows'] === $cteTopResult['rowsReturned'],
        'CTE TOP first-page optimization changed.'
    );

    $commentFile = $testDirectory . DIRECTORY_SEPARATOR . 'comment.sql';
    file_put_contents($commentFile, "-- reviewed resource\nSELECT 1 AS ResultValue;");
    $files[] = $commentFile;
    $commentRegistry = new SqlResourceRegistry([], $testDirectory);
    $commentEngine = new SqlResourceCapabilityEngine();
    (new SqlRepository($commentEngine, $commentRegistry))->execute(['resource' => 'comment']);
    resourceCapabilityAssert(
        str_contains(end($commentEngine->executions)['sql'], '-- reviewed resource'),
        'A leading-comment SELECT resource was rejected or discarded.'
    );

    $offsetFile = $testDirectory . DIRECTORY_SEPARATOR . 'offset.sql';
    file_put_contents($offsetFile, 'SELECT Id AS ResultValue FROM dbo.Events ORDER BY Id OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY;');
    $files[] = $offsetFile;
    $offsetRegistry = new SqlResourceRegistry([], $testDirectory);
    $offsetEngine = new SqlResourceCapabilityEngine();
    (new SqlRepository($offsetEngine, $offsetRegistry))->execute(['resource' => 'offset']);
    resourceCapabilityAssert(
        substr_count(end($offsetEngine->executions)['sql'], 'OFFSET 5 ROWS') === 1,
        'Authored OFFSET/FETCH was not executed directly.'
    );
    $offsetFailure = resourceCapabilityFailure(
        fn () => (new SqlRepository(new SqlResourceCapabilityEngine(), $offsetRegistry))->execute([
            'resource' => 'offset',
            'pagination' => ['page' => 1, 'pageSize' => 10],
        ]),
        'Runtime pagination was combined with authored OFFSET/FETCH.'
    );
    resourceCapabilityAssert(
        $offsetFailure instanceof ApiRequestException
            && $offsetFailure->getErrorCode() === 'INVALID_SQL_PAGINATION',
        'Authored pagination produced the wrong explicit failure.'
    );

    foreach (['SELECT Id AS ResultValue INTO dbo.Copy FROM dbo.Events',
        'SELECT 1 AS ResultValue; DELETE FROM dbo.Events',
        'WITH C AS (SELECT 1 AS Id) DELETE FROM dbo.Events'] as $index => $unsafeSql) {
        $file = $testDirectory . DIRECTORY_SEPARATOR . 'unsafe-' . $index . '.sql';
        file_put_contents($file, $unsafeSql);
        $files[] = $file;
        $unsafeRegistry = new SqlResourceRegistry([], $testDirectory);
        resourceCapabilityFailure(
            fn () => (new SqlRepository(new SqlResourceCapabilityEngine(), $unsafeRegistry))->execute(['resource' => 'unsafe-' . $index]),
            'A write-capable or multi-statement SQL resource was accepted.'
        );
    }

    $validator = new QueryRequestValidator();
    foreach (['table', 'schema', 'file', 'path', 'url', 'sql', 'username', 'password', 'credentials', 'connection'] as $property) {
        $request = ['action' => 'sql', 'resource' => 'cte', $property => 'client-controlled'];
        resourceCapabilityFailure(
            fn () => $validator->validate($request),
            "Client-controlled {$property} was accepted in SQL Resource Mode."
        );
    }

    echo "SQL resource capability tests passed.\n";
} finally {
    foreach ($files as $file) {
        @unlink($file);
    }
    @rmdir($testDirectory);
}
