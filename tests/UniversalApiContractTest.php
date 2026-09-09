<?php

require_once __DIR__ . '/../app/Requests/QueryRequestValidator.php';
require_once __DIR__ . '/../app/Requests/QueryRequestNormalizer.php';
require_once __DIR__ . '/../app/Repositories/Query/SelectBuilder.php';
require_once __DIR__ . '/../app/Repositories/Query/RoutineBuilder.php';
require_once __DIR__ . '/../core/Response.php';

class ContractTestEngine extends QueryEngine
{
    public function __construct() {}
    public function executePrepared($sql, array $params = [], array $context = [])
    {
        if (strpos($sql, 'compatibility_level') !== false) {
            return ['data' => [['CompatibilityLevel' => 100]]];
        }
        return ['data' => [['TotalRows' => 12]]];
    }
}

class ContractTestMetadata extends MetadataRepository
{
    public function __construct() {}
    public function tableExists($table) { return true; }
    public function columnExists($table, $column) { return $column !== 'Missing'; }
    public function getColumnDataType($table, $column) { return 'varchar'; }
    public function getColumns($table) { return ['data' => [['COLUMN_NAME' => 'ItemCode']]]; }
}

function contractAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function expectInvalid(QueryRequestValidator $validator, array $request): void
{
    try {
        $validator->validate($request);
    } catch (ApiRequestException $exception) {
        contractAssert($exception->getErrorCode() === 'INVALID_REQUEST', 'Wrong validation error code.');
        contractAssert($exception->getDetails() !== [], 'Validation details are missing.');
        return;
    }
    throw new RuntimeException('Invalid public request was accepted.');
}

function expectBuildInvalid(SelectBuilder $builder, array $request): void
{
    try {
        $builder->build($request);
    } catch (Exception $exception) {
        return;
    }
    throw new RuntimeException('Invalid normalized query was accepted by builder validation.');
}

$validator = new QueryRequestValidator();
$normalizer = new QueryRequestNormalizer();
$builder = new SelectBuilder(new ContractTestEngine(), new ContractTestMetadata());

// Covers SELECT, fields, aliases, multiple filters, JOIN, GROUP BY, HAVING,
// ASC/DESC multi-sort, pagination, aggregate functions, and prepared values.
$request = [
    'action' => 'select',
    'source' => ['table' => 'Items', 'alias' => 'I'],
    'fields' => [
        'I.ItemCode',
        ['field' => 'I.Amount', 'alias' => 'Price'],
        ['function' => 'COUNT', 'field' => 'I.ItemCode', 'alias' => 'Total']
    ],
    'filters' => [
        ['field' => 'I.Status', 'operator' => '=', 'value' => 'Active'],
        ['field' => 'I.Amount', 'operator' => 'BETWEEN', 'value' => [10, 20]]
    ],
    'joins' => [[
        'type' => 'LEFT',
        'source' => ['table' => 'Groups', 'alias' => 'G'],
        'on' => ['left' => 'I.GroupId', 'operator' => '=', 'right' => 'G.Id']
    ]],
    'groupBy' => ['I.ItemCode', 'I.Amount'],
    'having' => [['function' => 'COUNT', 'field' => 'I.ItemCode', 'operator' => '>', 'value' => 1]],
    'sort' => [
        ['field' => 'I.ItemCode', 'direction' => 'ASC'],
        ['field' => 'I.Amount', 'direction' => 'DESC']
    ],
    'pagination' => ['page' => 1, 'pageSize' => 50]
];
$validator->validate($request);
$internal = $normalizer->normalize($request);
contractAssert($internal['table'] === 'Items' && $internal['columns'][1]['column'] === 'I.Amount', 'Source/fields normalization failed.');
contractAssert($internal['where'][0]['column'] === 'I.Status', 'Filter normalization failed.');
contractAssert($internal['sort'][1] === ['column' => 'I.Amount', 'direction' => 'DESC'], 'Sort normalization failed.');
contractAssert($internal['page'] === 1 && $internal['pageSize'] === 50, 'Pagination normalization failed.');
$query = $builder->build($internal);
contractAssert(strpos($query['sql'], 'LEFT JOIN Groups G') !== false, 'JOIN SQL missing.');
contractAssert(strpos($query['sql'], 'GROUP BY I.ItemCode, I.Amount') !== false, 'GROUP BY SQL missing.');
contractAssert(strpos($query['sql'], 'HAVING COUNT(I.ItemCode) > ?') !== false, 'HAVING SQL missing.');
contractAssert(strpos($query['sql'], 'ORDER BY [ItemCode] ASC, [Amount] DESC') !== false, 'Pagination sort missing.');
contractAssert(strpos($query['sql'], 'ORDER BY 1') === false, 'Positional window ordering leaked from public contract.');
contractAssert($query['params'] === ['Active', 10, 20, 1], 'Prepared parameter order changed.');

$window = [
    'action' => 'select', 'source' => ['table' => 'Items'],
    'fields' => ['ItemCode', [
        'function' => 'ROW_NUMBER', 'alias' => 'RowNumber',
        'sort' => [['field' => 'ItemCode', 'direction' => 'ASC']]
    ]]
];
$validator->validate($window);
$windowSql = $builder->build($normalizer->normalize($window))['sql'];
contractAssert(strpos($windowSql, 'ROW_NUMBER() OVER (ORDER BY ItemCode ASC)') !== false, 'Window function failed.');

$functionRequest = [
    'action' => 'select', 'source' => ['table' => 'Items'],
    'fields' => [['function' => 'UPPER', 'field' => 'ItemCode', 'alias' => 'UpperCode']]
];
$validator->validate($functionRequest);
contractAssert(strpos($builder->build($normalizer->normalize($functionRequest))['sql'], 'UPPER(ItemCode) AS [UpperCode]') !== false, 'SQL function failed.');

$advancedFields = [
    'action' => 'select', 'source' => ['table' => 'Items'], 'distinct' => true, 'limit' => 5,
    'fields' => [
        ['case' => ['when' => [[
            'condition' => ['field' => 'Status', 'operator' => '=', 'value' => "O'Brien"],
            'then' => 'Open'
        ]], 'else' => 'Closed'], 'alias' => 'StatusLabel'],
        ['expression' => ['left' => 'Amount', 'operator' => '+', 'right' => 1], 'alias' => 'AdjustedAmount'],
        ['function' => 'COALESCE', 'fields' => ['ItemCode', 'Description'], 'default' => '', 'alias' => 'DisplayCode']
    ]
];
$validator->validate($advancedFields);
$advancedSql = $builder->build($normalizer->normalize($advancedFields))['sql'];
contractAssert(strpos($advancedSql, 'DISTINCT TOP 5') !== false, 'DISTINCT/limit failed.');
contractAssert(strpos($advancedSql, 'END AS [StatusLabel]') !== false, 'CASE alias failed.');
contractAssert(strpos($advancedSql, "Status = 'O''Brien'") !== false, 'CASE escaping failed.');
contractAssert(strpos($advancedSql, '(Amount + 1) AS [AdjustedAmount]') !== false, 'Expression failed.');
contractAssert(strpos($advancedSql, 'COALESCE(ItemCode, Description') !== false, 'Multi-field function failed.');

$subquery = [
    'action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['ItemCode'],
    'filters' => [[
        'field' => 'GroupId', 'operator' => 'IN',
        'query' => ['source' => ['table' => 'Groups'], 'fields' => ['Id']]
    ]]
];
$validator->validate($subquery);
contractAssert(strpos($builder->build($normalizer->normalize($subquery))['sql'], 'GroupId IN (') !== false, 'Subquery failed.');

$exists = [
    'action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['ItemCode'],
    'filters' => [['operator' => 'EXISTS', 'query' => ['source' => ['table' => 'Groups'], 'fields' => ['Id']]]]
];
$validator->validate($exists);
contractAssert(strpos($builder->build($normalizer->normalize($exists))['sql'], 'EXISTS (') !== false, 'EXISTS failed.');

$cte = [
    'action' => 'select', 'source' => ['table' => 'ActiveItems'], 'fields' => ['ItemCode'],
    'with' => ['name' => 'ActiveItems', 'query' => ['source' => ['table' => 'Items'], 'fields' => ['ItemCode']]]
];
$validator->validate($cte);
contractAssert(strpos($builder->build($normalizer->normalize($cte))['sql'], 'WITH ActiveItems AS (') !== false, 'CTE failed.');

$recursiveCte = [
    'action' => 'select', 'source' => ['table' => 'NumberTree'], 'fields' => ['Id'],
    'with' => [
        'name' => 'NumberTree',
        'anchor' => ['source' => ['table' => 'Seed'], 'fields' => ['Id']],
        'recursive' => ['source' => ['table' => 'NumberTree'], 'fields' => ['Id']]
    ]
];
$validator->validate($recursiveCte);
contractAssert(strpos($builder->build($normalizer->normalize($recursiveCte))['sql'], 'WITH NumberTree AS (') !== false, 'Recursive CTE failed.');

foreach (['union', 'unionAll'] as $action) {
    $setRequest = ['action' => $action, 'queries' => [
        ['source' => ['table' => 'Items'], 'fields' => ['ItemCode']],
        ['source' => ['table' => 'Archive'], 'fields' => ['ItemCode']]
    ]];
    $validator->validate($setRequest);
    $setInternal = $normalizer->normalize($setRequest);
    contractAssert($setInternal['type'] === ($action === 'unionAll' ? 'UNION ALL' : 'UNION'), 'Set operation failed.');
}

$routine = new RoutineBuilder();
foreach ([
    ['action' => 'procedure', 'source' => ['procedure' => 'RunReport'], 'parameters' => [1]],
    ['action' => 'function', 'source' => ['function' => 'dbo.Score'], 'parameters' => [1]],
    ['action' => 'tableFunction', 'source' => ['function' => 'dbo.Rows'], 'parameters' => [1]]
] as $routineRequest) {
    $validator->validate($routineRequest);
    $routineInternal = $normalizer->normalize($routineRequest);
    contractAssert($routineInternal['params'] === [1], 'Routine parameter normalization failed.');
    if ($routineRequest['action'] === 'procedure') {
        contractAssert($routine->buildProcedure($routineInternal)['sql'] === 'EXEC RunReport ?', 'Procedure failed.');
    } elseif ($routineRequest['action'] === 'function') {
        contractAssert($routine->buildFunction($routineInternal)['sql'] === 'SELECT dbo.Score(?) AS Result', 'Scalar function failed.');
    } else {
        contractAssert($routine->buildTableFunction($routineInternal)['sql'] === 'SELECT * FROM dbo.Rows(?)', 'Table function failed.');
    }
}

foreach (['metadata.tables', 'metadata.views', 'metadata.procedures', 'metadata.schema'] as $metadataAction) {
    $validator->validate(['action' => $metadataAction]);
    contractAssert($normalizer->normalize(['action' => $metadataAction])['controller'] === 'Metadata', 'Metadata failed.');
}
$validator->validate(['action' => 'metadata.columns', 'source' => ['table' => 'Items']]);

expectInvalid($validator, []);
expectInvalid($validator, ['action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['bad;field']]);
expectInvalid($validator, ['action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['Id'], 'sort' => [['field' => '1', 'direction' => 'ASC']]]);
expectInvalid($validator, ['action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['Id'], 'filters' => [['field' => 'Id', 'operator' => 'DROP', 'value' => 1]]]);
expectInvalid($validator, ['action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['Id'], 'pagination' => ['page' => 0, 'pageSize' => 10]]);
expectInvalid($validator, ['controller' => 'Query', 'action' => 'select', 'table' => 'Items', 'columns' => ['Id']]);
expectBuildInvalid($builder, $normalizer->normalize([
    'action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['ItemCode'],
    'sort' => [['field' => 'Missing', 'direction' => 'ASC']]
]));

Response::setRequestContext(['pagination' => ['page' => 1, 'pageSize' => 50]]);
$empty = Response::successPayload(['executionTime' => 1.2, 'rowsReturned' => 0, 'totalRows' => 0, 'data' => []], 'Loaded');
contractAssert($empty['data'] === [] && $empty['meta']['totalRows'] === 0, 'Empty result contract failed.');
$error = Response::errorPayload('Query execution failed.', 'QUERY_ERROR');
contractAssert($error['data'] === [] && $error['error']['code'] === 'QUERY_ERROR', 'Database error contract failed.');

echo "Universal API contract tests passed.\n";
