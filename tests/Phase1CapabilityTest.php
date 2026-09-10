<?php

require_once __DIR__ . '/../app/Requests/QueryRequestValidator.php';
require_once __DIR__ . '/../app/Requests/QueryRequestNormalizer.php';
require_once __DIR__ . '/../app/Repositories/QueryRepository.php';
require_once __DIR__ . '/../app/Repositories/MetadataRepository.php';

function phase1Assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase1ExpectInvalid(QueryRequestValidator $validator, array $request, string $path): void
{
    try {
        $validator->validate($request);
    } catch (ApiRequestException $exception) {
        phase1Assert($exception->getErrorCode() === 'INVALID_REQUEST', 'Wrong validation error code.');
        phase1Assert(
            array_filter($exception->getDetails(), fn (array $detail): bool => $detail['path'] === $path) !== [],
            "Expected validation error at {$path}."
        );
        return;
    }
    throw new RuntimeException("Invalid request was accepted: {$path}");
}

class Phase1Engine extends QueryEngine
{
    public array $executions = [];
    public function __construct() {}
    public function executePrepared($sql, array $params = [], array $context = [])
    {
        $this->executions[] = ['sql' => $sql, 'params' => $params, 'context' => $context];
        if (str_contains($sql, 'compatibility_level')) return ['data' => [['CompatibilityLevel' => 150]]];
        if (str_contains($sql, 'COUNT(*) AS TotalRows')) return ['data' => [['TotalRows' => 4]]];
        return ['executionTime' => 0.2, 'rowsReturned' => 1, 'data' => [['Id' => 1]]];
    }
    public function executePreparedQuery($sql, array $params = [], array $context = [])
    {
        return $this->executePrepared($sql, $params, $context);
    }
}

class Phase1Metadata extends MetadataRepository
{
    public function __construct() {}
    public function tableExists($table)
    {
        return !in_array($table, ['ActiveItems', 'NumberTree'], true);
    }
    public function columnExists($table, $column)
    {
        return $column !== 'Missing';
    }
    public function getColumnDataType($table, $column) { return 'varchar'; }
    public function getColumns($table)
    {
        $columns = [
            ['COLUMN_NAME' => 'Id'],
            ['COLUMN_NAME' => 'Name'],
            ['COLUMN_NAME' => 'Amount'],
        ];
        if ($table === 'Archive') array_pop($columns);
        return ['data' => $columns];
    }
}

class Phase1MetadataEngine extends QueryEngine
{
    public array $operations = [];
    public function __construct() {}
    public function executeFile($file)
    {
        $this->operations[] = ['file' => $file];
        return ['data' => [['ok' => 1]]];
    }
    public function getQuery($file)
    {
        $this->operations[] = ['queryFile' => $file];
        return 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?';
    }
    public function executePrepared($sql, array $params = [], array $context = [])
    {
        $this->operations[] = ['sql' => $sql, 'params' => $params, 'context' => $context];
        return ['data' => [['ok' => 1]]];
    }
}

$validator = new QueryRequestValidator();
$normalizer = new QueryRequestNormalizer();
$engine = new Phase1Engine();
$repository = new QueryRepository($engine, new Phase1Metadata());

$build = function (array $request) use ($validator, $normalizer, $repository): array {
    $validator->validate($request);
    return $repository->buildSelect($normalizer->normalize($request));
};

// Every function documented as public is accepted and reaches its SQL renderer.
$functionCases = [
    [['function' => 'COUNT', 'field' => '*'], 'COUNT(*)'],
    [['function' => 'SUM', 'field' => 'Amount'], 'SUM(Amount)'],
    [['function' => 'AVG', 'field' => 'Amount'], 'AVG(Amount)'],
    [['function' => 'MIN', 'field' => 'Amount'], 'MIN(Amount)'],
    [['function' => 'MAX', 'field' => 'Amount'], 'MAX(Amount)'],
    [['function' => 'STRING_AGG', 'field' => 'Name', 'separator' => ','], "STRING_AGG(Name, ',')"],
    [['function' => 'UPPER', 'field' => 'Name'], 'UPPER(Name)'],
    [['function' => 'LOWER', 'field' => 'Name'], 'LOWER(Name)'],
    [['function' => 'LTRIM', 'field' => 'Name'], 'LTRIM(Name)'],
    [['function' => 'RTRIM', 'field' => 'Name'], 'RTRIM(Name)'],
    [['function' => 'TRIM', 'field' => 'Name'], 'TRIM(Name)'],
    [['function' => 'LEN', 'field' => 'Name'], 'LEN(Name)'],
    [['function' => 'COALESCE', 'fields' => ['Name', 'Id'], 'default' => 'n/a'], 'COALESCE(Name, Id'],
    [['function' => 'ISNULL', 'field' => 'Name', 'default' => 'n/a'], 'ISNULL(Name'],
    [['function' => 'CAST', 'field' => 'Amount', 'datatype' => 'decimal(10,2)'], 'CAST(Amount AS DECIMAL(10,2))'],
    [['function' => 'CONVERT', 'field' => 'Amount', 'datatype' => 'varchar(20)'], 'CONVERT(VARCHAR(20), Amount'],
    [['function' => 'NULLIF', 'field' => 'Amount', 'value' => 0], 'NULLIF(Amount, 0)'],
    [['function' => 'CONCAT', 'fields' => ['Name', 'Id']], 'CONCAT(Name, Id)'],
    [['function' => 'LEFT', 'field' => 'Name', 'length' => 2], 'LEFT(Name, 2)'],
    [['function' => 'RIGHT', 'field' => 'Name', 'length' => 2], 'RIGHT(Name, 2)'],
    [['function' => 'SUBSTRING', 'field' => 'Name', 'start' => 1, 'length' => 2], 'SUBSTRING(Name, 1, 2)'],
    [['function' => 'REPLACE', 'field' => 'Name', 'search' => 'a', 'replace' => 'b'], "REPLACE(Name, 'a', 'b')"],
    [['function' => 'CHARINDEX', 'field' => 'Name', 'search' => 'a'], "CHARINDEX('a', Name)"],
    [['function' => 'PATINDEX', 'field' => 'Name', 'pattern' => '%a%'], "PATINDEX('%a%', CAST(Name"],
    [['function' => 'FORMAT', 'field' => 'Amount', 'format' => 'N2'], "FORMAT(Amount, 'N2')"],
    [['function' => 'YEAR', 'field' => 'Id'], 'YEAR(CONVERT(date'],
    [['function' => 'MONTH', 'field' => 'Id'], 'MONTH(CONVERT(date'],
    [['function' => 'DAY', 'field' => 'Id'], 'DAY(CONVERT(date'],
    [['function' => 'DATEPART', 'field' => 'Id', 'part' => 'month'], 'DATEPART(MONTH'],
    [['function' => 'DATENAME', 'field' => 'Id', 'part' => 'month'], 'DATENAME(MONTH'],
    [['function' => 'GETDATE'], 'GETDATE()'],
    [['function' => 'DATEADD', 'field' => 'Id', 'datepart' => 'day', 'number' => 1], 'DATEADD(DAY, 1'],
    [['function' => 'DATEDIFF', 'datepart' => 'day', 'start' => ['field' => 'Id'], 'end' => ['function' => 'GETDATE']], 'DATEDIFF(DAY'],
    [['function' => 'EOMONTH', 'start' => ['field' => 'Id']], 'EOMONTH(Id, 0)'],
    [['function' => 'ISDATE', 'field' => 'Name'], 'ISDATE(Name)'],
    [['function' => 'DATEFROMPARTS', 'year' => 2026, 'month' => 1, 'day' => 2], 'DATEFROMPARTS(2026, 1, 2)'],
    [['function' => 'DATETIMEFROMPARTS', 'year' => 2026, 'month' => 1, 'day' => 2, 'hour' => 3, 'minute' => 4, 'second' => 5, 'millisecond' => 6], 'DATETIMEFROMPARTS(2026, 1, 2, 3, 4, 5, 6)'],
    [['function' => 'SYSDATETIME'], 'SYSDATETIME()'],
    [['function' => 'CURRENT_TIMESTAMP'], 'CURRENT_TIMESTAMP'],
    [['function' => 'IIF', 'condition' => ['left' => ['field' => 'Amount'], 'operator' => '>', 'right' => 0], 'true' => 'yes', 'false' => 'no'], 'IIF(Amount > 0'],
    [['function' => 'CHOOSE', 'index' => 1, 'values' => ['one', 'two']], "CHOOSE(1, 'one', 'two')"],
    [['function' => 'ABS', 'field' => 'Amount'], 'ABS(Amount)'],
    [['function' => 'ROUND', 'field' => 'Amount', 'precision' => 2], 'ROUND(Amount, 2)'],
    [['function' => 'CEILING', 'field' => 'Amount'], 'CEILING(Amount)'],
    [['function' => 'FLOOR', 'field' => 'Amount'], 'FLOOR(Amount)'],
    [['function' => 'POWER', 'field' => 'Amount', 'power' => 2], 'POWER(Amount, 2)'],
    [['function' => 'SQRT', 'field' => 'Amount'], 'SQRT(Amount)'],
    [['function' => 'EXP', 'field' => 'Amount'], 'EXP(Amount)'],
    [['function' => 'LOG', 'field' => 'Amount'], 'LOG(Amount)'],
];
foreach ($functionCases as $index => [$field, $fragment]) {
    $field['alias'] = 'Value' . $index;
    try {
        $query = $build(['action' => 'select', 'source' => ['table' => 'Items'], 'fields' => [$field]]);
    } catch (Throwable $exception) {
        throw new RuntimeException("{$field['function']} failed: {$exception->getMessage()}", 0, $exception);
    }
    phase1Assert(str_contains($query['sql'], $fragment), "Function SQL missing: {$fragment}");
}

// Unsafe or incomplete option shapes fail public validation rather than execution.
$invalidBase = ['action' => 'select', 'source' => ['table' => 'Items']];
phase1ExpectInvalid($validator, $invalidBase + ['fields' => [[
    'function' => 'DATEPART', 'field' => 'Id', 'part' => 'month); DROP TABLE X;--'
]]], 'fields.0.part');
phase1ExpectInvalid($validator, $invalidBase + ['fields' => [[
    'function' => 'DATEDIFF', 'datepart' => 'day',
    'start' => ['function' => 'DROP'], 'end' => ['function' => 'GETDATE']
]]], 'fields.0.start');
phase1ExpectInvalid($validator, $invalidBase + ['fields' => [[
    'function' => 'IIF',
    'condition' => ['left' => ['field' => 'Id;DROP'], 'operator' => '=', 'right' => 1],
    'true' => 1, 'false' => 0
]]], 'fields.0.condition');
phase1ExpectInvalid($validator, $invalidBase + ['fields' => [[
    'case' => ['when' => [[
        'condition' => ['field' => 'Id', 'operator' => '=', 'value' => ['field' => 'Name']],
        'then' => 'yes',
    ]]],
]]], 'fields.0.case.when.0');
phase1ExpectInvalid($validator, $invalidBase + ['fields' => [[
    'function' => 'NTILE', 'buckets' => 0, 'sort' => [['field' => 'Id']]
]]], 'fields.0.buckets');
phase1ExpectInvalid($validator, $invalidBase + ['fields' => [[
    'function' => 'TIMEFROMPARTS', 'hour' => 1, 'minute' => 2, 'second' => 3,
    'fractions' => 0, 'precision' => 0
]]], 'fields.0.fractions');

// Nested SELECTs support filter subqueries, but not ignored/broken nested clauses.
phase1ExpectInvalid($validator, $invalidBase + ['fields' => ['Id'], 'filters' => [[
    'field' => 'Id', 'operator' => 'IN',
    'query' => ['source' => ['table' => 'Other'], 'fields' => ['Id', 'Name']]
]]], 'filters.0.query.fields');
phase1ExpectInvalid($validator, $invalidBase + ['fields' => ['Id'], 'filters' => [[
    'field' => 'Id', 'operator' => 'IN',
    'query' => ['source' => ['table' => 'Other'], 'fields' => ['Id'], 'sort' => [['field' => 'Id']]]
]]], 'filters.0.query.sort');

// A nested subquery must not overwrite the outer alias scope used later by GROUP/ORDER.
$isolated = $build([
    'action' => 'select', 'source' => ['table' => 'Items', 'alias' => 'I'],
    'fields' => ['I.Id'],
    'filters' => [['field' => 'I.Id', 'operator' => 'IN',
        'query' => ['source' => ['table' => 'Other', 'alias' => 'O'], 'fields' => ['O.Id']]]],
    'groupBy' => ['I.Id'],
    'sort' => [['field' => 'I.Id', 'direction' => 'ASC']],
]);
phase1Assert(str_contains($isolated['sql'], 'GROUP BY I.Id ORDER BY I.Id ASC'), 'Nested query corrupted outer aliases.');

// JOIN columns are checked against their resolved base/join tables.
try {
    $build([
        'action' => 'select', 'source' => ['table' => 'Items', 'alias' => 'I'], 'fields' => ['I.Id'],
        'joins' => [['type' => 'INNER', 'source' => ['table' => 'Other', 'alias' => 'O'],
            'on' => ['left' => 'I.Id', 'right' => 'O.Missing']]],
    ]);
    throw new RuntimeException('Invalid JOIN column was accepted.');
} catch (Exception $exception) {
    phase1Assert(str_contains($exception->getMessage(), 'Invalid JOIN column'), 'Wrong JOIN-column failure.');
}

try {
    $build($invalidBase + ['fields' => [[
        'function' => 'IIF',
        'condition' => ['left' => ['field' => 'Missing'], 'operator' => '=', 'right' => 1],
        'true' => 1, 'false' => 0,
    ]]]);
    throw new RuntimeException('Unknown nested expression column was accepted.');
} catch (Exception $exception) {
    phase1Assert(str_contains($exception->getMessage(), 'Invalid expression column'), 'Wrong nested-column failure.');
}

// Standard CTE output fields use inferred CTE metadata, including count pagination.
$cteRequest = [
    'action' => 'select', 'source' => ['table' => 'ActiveItems'], 'fields' => ['Id'],
    'with' => ['name' => 'ActiveItems', 'query' => [
        'source' => ['table' => 'Items'], 'fields' => ['Id', ['field' => 'Name', 'alias' => 'DisplayName']]
    ]],
    'filters' => [['field' => 'DisplayName', 'operator' => 'LIKE', 'value' => 'A%']],
    'sort' => [['field' => 'Id', 'direction' => 'ASC']],
    'pagination' => ['page' => 1, 'pageSize' => 10],
];
$cte = $build($cteRequest);
phase1Assert(str_starts_with(trim($cte['sql']), 'WITH ActiveItems AS ('), 'CTE SQL prefix missing.');
$cteCount = array_values(array_filter(
    $engine->executions,
    fn (array $entry): bool => str_contains($entry['sql'], 'COUNT(*) AS TotalRows')
        && str_contains($entry['sql'], 'ActiveItems')
));
phase1Assert($cteCount !== [] && str_starts_with(trim($cteCount[0]['sql']), 'WITH ActiveItems AS ('), 'CTE count lacks WITH scope.');

try {
    $build(array_replace($cteRequest, ['fields' => ['NotProjected']]));
    throw new RuntimeException('Unknown CTE output field was accepted.');
} catch (Exception $exception) {
    phase1Assert(str_contains($exception->getMessage(), 'Invalid column'), 'Wrong CTE output-field failure.');
}

// Recursive branches can reference the CTE's anchor projection.
$recursive = $build([
    'action' => 'select', 'source' => ['table' => 'NumberTree'], 'fields' => ['Id'],
    'with' => [
        'name' => 'NumberTree',
        'anchor' => ['source' => ['table' => 'Seed'], 'fields' => ['Id']],
        'recursive' => ['source' => ['table' => 'NumberTree'], 'fields' => ['Id']],
    ],
]);
phase1Assert(str_contains($recursive['sql'], 'UNION ALL'), 'Recursive CTE UNION ALL missing.');
phase1Assert(preg_match('/FROM\s+NumberTree/', $recursive['sql']) === 1, 'Recursive CTE self-reference missing.');
phase1ExpectInvalid($validator, [
    'action' => 'select', 'source' => ['table' => 'NumberTree'], 'fields' => ['Id'],
    'with' => [
        'name' => 'NumberTree',
        'anchor' => ['source' => ['table' => 'Seed'], 'fields' => ['Id']],
        'recursive' => ['source' => ['table' => 'NumberTree'], 'fields' => ['Id', 'Name']],
    ],
], 'with');

// UNION and UNION ALL use the real branch builder and execution path.
foreach (['union' => 'UNION', 'unionAll' => 'UNION ALL'] as $action => $operator) {
    $result = $repository->select($normalizer->normalize([
        'action' => $action,
        'queries' => [
            ['source' => ['table' => 'Items'], 'fields' => ['Id']],
            ['source' => ['table' => 'Archive'], 'fields' => ['Id']],
        ],
    ]));
    $execution = end($engine->executions);
    phase1Assert(str_contains($execution['sql'], " {$operator} "), "{$operator} execution SQL missing.");
    phase1Assert($result['rowsReturned'] === 1, "{$operator} result was not returned.");
}
phase1ExpectInvalid($validator, [
    'action' => 'union',
    'queries' => [
        ['source' => ['table' => 'Items'], 'fields' => ['Id']],
        ['source' => ['table' => 'Archive'], 'fields' => ['Id', 'Name']],
    ],
], 'queries');
phase1ExpectInvalid($validator, [
    'action' => 'union',
    'queries' => [[
        'source' => ['table' => 'Items'], 'fields' => ['Id'],
        'pagination' => ['page' => 1, 'pageSize' => 10],
    ]],
], 'queries.0.pagination');
try {
    $wildcardUnion = [
        'action' => 'union',
        'queries' => [
            ['source' => ['table' => 'Items'], 'fields' => ['*']],
            ['source' => ['table' => 'Archive'], 'fields' => ['*']],
        ],
    ];
    $validator->validate($wildcardUnion);
    $repository->select($normalizer->normalize($wildcardUnion));
    throw new RuntimeException('Wildcard UNION with incompatible projections was accepted.');
} catch (Exception $exception) {
    phase1Assert(
        str_contains($exception->getMessage(), 'same number of columns'),
        'Wrong wildcard UNION compatibility failure.'
    );
}

// Routine calls reach the prepared execution path with positional parameters.
foreach ([
    ['action' => 'procedure', 'source' => ['procedure' => 'dbo.RunReport'], 'parameters' => [1]],
    ['action' => 'function', 'source' => ['function' => 'dbo.Score'], 'parameters' => [1]],
    ['action' => 'tableFunction', 'source' => ['function' => 'dbo.Rows'], 'parameters' => [1]],
] as $request) {
    $validator->validate($request);
    $internal = $normalizer->normalize($request);
    $result = $repository->{$request['action']}($internal);
    $execution = end($engine->executions);
    phase1Assert($execution['params'] === [1] && $result['rowsReturned'] === 1, 'Routine execution path failed.');
}

// All five promised metadata reads reach their repository execution paths.
$metadataEngine = new Phase1MetadataEngine();
$metadataRepository = new MetadataRepository($metadataEngine);
phase1Assert($metadataRepository->getTables()['data'][0]['ok'] === 1, 'Tables metadata failed.');
phase1Assert($metadataRepository->getViews()['data'][0]['ok'] === 1, 'Views metadata failed.');
phase1Assert($metadataRepository->getProcedures()['data'][0]['ok'] === 1, 'Procedures metadata failed.');
phase1Assert($metadataRepository->schema()['data'][0]['ok'] === 1, 'Schema metadata failed.');
phase1Assert($metadataRepository->getColumns('Items')['data'][0]['ok'] === 1, 'Columns metadata failed.');
$columnOperation = end($metadataEngine->operations);
phase1Assert(
    $columnOperation['params'] === ['Items']
        && $columnOperation['context']['queryPhase'] === 'metadata',
    'Columns metadata was not prepared with the table parameter.'
);

echo "Phase 1 capability tests passed.\n";
