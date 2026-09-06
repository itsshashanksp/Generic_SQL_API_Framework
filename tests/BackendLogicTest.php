<?php

require_once __DIR__ . '/../app/Requests/QueryRequestValidator.php';
require_once __DIR__ . '/../app/Requests/QueryRequestNormalizer.php';
require_once __DIR__ . '/../app/Repositories/Query/SelectBuilder.php';
require_once __DIR__ . '/../app/Repositories/QueryRepository.php';
require_once __DIR__ . '/../app/Repositories/SetOperationBuilder.php';
require_once __DIR__ . '/../core/Response.php';

class LogicTestEngine extends QueryEngine
{
    public array $executions = [];
    private int $compatibilityLevel;

    public function __construct(int $compatibilityLevel = 150)
    {
        $this->compatibilityLevel = $compatibilityLevel;
    }

    public function executePrepared($sql, array $params = [])
    {
        $this->executions[] = ['sql' => $sql, 'params' => $params];
        if (strpos($sql, 'compatibility_level') !== false) {
            return ['data' => [['CompatibilityLevel' => $this->compatibilityLevel]]];
        }
        if (strpos($sql, 'COUNT(*) AS TotalRows') !== false) {
            return ['data' => [['TotalRows' => 37]]];
        }
        return ['executionTime' => 0.25, 'rowsReturned' => 1, 'data' => [['ok' => 1]]];
    }
}

class LogicTestMetadata extends MetadataRepository
{
    public function __construct() {}
    public function tableExists($table) { return $table !== 'MissingTable'; }
    public function columnExists($table, $column) { return $column !== 'Missing'; }
    public function getColumnDataType($table, $column)
    {
        return $column === 'NumericDate' ? 'int' : 'varchar';
    }
    public function getColumns($tableName)
    {
        return ['data' => [
            ['COLUMN_NAME' => 'Id'],
            ['COLUMN_NAME' => 'Name'],
            ['COLUMN_NAME' => 'Amount']
        ]];
    }
}

class LogicTestRepository extends QueryRepository
{
    public function __construct() {}
    public function buildSelect($request, bool $isUnion = false)
    {
        return ['sql' => 'SELECT ' . $request['columns'][0] . ' FROM ' . $request['table'], 'params' => $request['params'] ?? []];
    }
}

function logicAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function logicContains(string $needle, string $haystack, string $message): void
{
    logicAssert(strpos($haystack, $needle) !== false, $message . "\nMissing: {$needle}\nSQL: {$haystack}");
}

$validator = new QueryRequestValidator();
$normalizer = new QueryRequestNormalizer();
$engine = new LogicTestEngine();
$builder = new SelectBuilder($engine, new LogicTestMetadata());

$buildPublic = function (array $request, bool $isUnion = true) use ($validator, $normalizer, $builder): array {
    $validator->validate($request);
    return $builder->build($normalizer->normalize($request), $isUnion);
};

// Every public WHERE operator is validated, normalized, and rendered with the
// expected prepared parameters. EXISTS variants are covered by the contract test.
$operatorCases = [
    ['=', 5, 'Id = ?', [5]], ['!=', 5, 'Id != ?', [5]], ['<>', 5, 'Id <> ?', [5]],
    ['>', 5, 'Id > ?', [5]], ['<', 5, 'Id < ?', [5]], ['>=', 5, 'Id >= ?', [5]],
    ['<=', 5, 'Id <= ?', [5]], ['LIKE', 'A%', 'Name LIKE ?', ['A%']],
    ['NOT LIKE', 'A%', 'Name NOT LIKE ?', ['A%']],
    ['IN', [1, 2], 'Id IN (?, ?)', [1, 2]], ['NOT IN', [1, 2], 'Id NOT IN (?, ?)', [1, 2]],
    ['BETWEEN', [1, 9], 'Id BETWEEN ? AND ?', [1, 9]],
    ['NOT BETWEEN', [1, 9], 'Id NOT BETWEEN ? AND ?', [1, 9]],
    ['IS NULL', null, 'Name IS NULL', []], ['IS NOT NULL', null, 'Name IS NOT NULL', []]
];
foreach ($operatorCases as [$operator, $value, $fragment, $params]) {
    $filter = ['field' => str_contains($fragment, 'Name') ? 'Name' : 'Id', 'operator' => $operator];
    if (!in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
        $filter['value'] = $value;
    }
    $query = $buildPublic(['action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['Id'], 'filters' => [$filter]]);
    logicContains($fragment, $query['sql'], "{$operator} SQL generation failed.");
    logicAssert($query['params'] === $params, "{$operator} parameter generation failed.");
}

$dateRange = $buildPublic([
    'action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['Id'],
    'filters' => [['field' => 'NumericDate', 'operator' => 'BETWEEN', 'value' => ['2026-01-02', '2026-02-03']]]
]);
logicAssert($dateRange['params'] === [20260102, 20260203], 'Integer-backed date normalization failed.');

$orQuery = $buildPublic([
    'action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['Id'], 'filterLogic' => 'OR',
    'filters' => [['field' => 'Id', 'operator' => '=', 'value' => 1], ['field' => 'Id', 'operator' => '=', 'value' => 2]]
]);
logicContains('Id = ? OR Id = ?', $orQuery['sql'], 'OR filter logic failed.');

foreach (['EXISTS', 'NOT EXISTS'] as $existsOperator) {
    $query = $buildPublic([
        'action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['Id'],
        'filters' => [[
            'operator' => $existsOperator,
            'query' => ['source' => ['table' => 'Stock'], 'fields' => ['Id']]
        ]]
    ]);
    logicContains($existsOperator . ' (', $query['sql'], "{$existsOperator} subquery generation failed.");
}

foreach (['INNER', 'LEFT', 'RIGHT'] as $joinType) {
    $query = $buildPublic([
        'action' => 'select', 'source' => ['table' => 'Items', 'alias' => 'I'], 'fields' => ['I.Id'],
        'joins' => [['type' => $joinType, 'source' => ['table' => 'Groups', 'alias' => 'G'],
            'on' => ['left' => 'I.GroupId', 'operator' => '=', 'right' => 'G.Id']]]
    ]);
    logicContains("{$joinType} JOIN Groups G", $query['sql'], "{$joinType} JOIN generation failed.");
}

// Representative coverage for every public function family.
$functionFields = [
    ['function' => 'COUNT', 'field' => '*', 'alias' => 'CountValue'],
    ['function' => 'STRING_AGG', 'field' => 'Name', 'separator' => ',', 'alias' => 'Names'],
    ['function' => 'UPPER', 'field' => 'Name', 'alias' => 'UpperName'],
    ['function' => 'ISNULL', 'field' => 'Name', 'default' => '', 'alias' => 'SafeName'],
    ['function' => 'CAST', 'field' => 'Amount', 'datatype' => 'decimal(10,2)', 'alias' => 'CastAmount'],
    ['function' => 'CONVERT', 'field' => 'NumericDate', 'datatype' => 'date', 'style' => 112, 'alias' => 'DateValue'],
    ['function' => 'DATEADD', 'field' => 'NumericDate', 'datepart' => 'day', 'number' => 1, 'style' => 112, 'alias' => 'Tomorrow'],
    ['function' => 'DATEDIFF', 'datepart' => 'day', 'start' => ['field' => 'NumericDate'], 'end' => ['function' => 'GETDATE'], 'alias' => 'Age'],
    ['function' => 'ABS', 'field' => 'Amount', 'alias' => 'AbsoluteAmount'],
    ['function' => 'POWER', 'field' => 'Amount', 'power' => 2, 'alias' => 'Squared'],
    ['function' => 'IIF', 'condition' => ['left' => ['field' => 'Amount'], 'operator' => '>', 'right' => 0], 'true' => 'yes', 'false' => 'no', 'alias' => 'Positive']
];
$functionQuery = $buildPublic(['action' => 'select', 'source' => ['table' => 'Items'], 'fields' => $functionFields]);
foreach (['COUNT(*)', "STRING_AGG(Name, ',')", 'UPPER(Name)', 'ISNULL(Name', 'CAST(Amount AS DECIMAL(10,2))',
    'CONVERT(DATE, NumericDate, 112)', 'DATEADD(DAY, 1', 'DATEDIFF(DAY', 'ABS(Amount)', 'POWER(Amount, 2)', 'IIF(Amount > 0'] as $fragment) {
    logicContains($fragment, $functionQuery['sql'], "Function SQL generation failed for {$fragment}.");
}

$windowDefinitions = [
    ['function' => 'ROW_NUMBER', 'alias' => 'RowNo'],
    ['function' => 'RANK', 'alias' => 'RankNo'],
    ['function' => 'DENSE_RANK', 'alias' => 'DenseRankNo'],
    ['function' => 'NTILE', 'buckets' => 4, 'alias' => 'Quartile'],
    ['function' => 'LAG', 'field' => 'Amount', 'offset' => 2, 'default' => 0, 'alias' => 'PreviousAmount'],
    ['function' => 'LEAD', 'field' => 'Amount', 'alias' => 'NextAmount'],
    ['function' => 'FIRST_VALUE', 'field' => 'Amount', 'alias' => 'FirstAmount'],
    ['function' => 'LAST_VALUE', 'field' => 'Amount', 'alias' => 'LastAmount']
];
foreach ($windowDefinitions as $definition) {
    $definition['sort'] = [['field' => 'Id', 'direction' => 'DESC']];
    $query = $buildPublic(['action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['Id', $definition]]);
    logicContains(strtoupper($definition['function']) . '(', $query['sql'], $definition['function'] . ' window generation failed.');
    logicContains('OVER (ORDER BY Id DESC', $query['sql'], $definition['function'] . ' window ordering failed.');
    logicAssert(strpos($query['sql'], 'OVER (ORDER BY 1') === false, 'Integer window ordering was generated.');
}

$paged = $buildPublic([
    'action' => 'select', 'source' => ['table' => 'Items'], 'fields' => ['Id', 'Name'],
    'sort' => [['field' => 'Name', 'direction' => 'DESC']], 'pagination' => ['page' => 3, 'pageSize' => 10]
], false);
logicContains('OFFSET 20 ROWS', $paged['sql'], 'OFFSET pagination failed.');
logicContains('FETCH NEXT 10 ROWS ONLY', $paged['sql'], 'FETCH pagination failed.');
logicAssert($paged['totalRows'] === 37, 'Pagination total-row count failed.');

$setEngine = new LogicTestEngine();
$setBuilder = new SetOperationBuilder(new LogicTestRepository(), $setEngine);
$setBuilder->build(['type' => 'UNION ALL', 'queries' => [
    ['table' => 'Items', 'columns' => ['Id'], 'params' => [1]],
    ['table' => 'Archive', 'columns' => ['Id'], 'params' => [2]]
]]);
$setExecution = end($setEngine->executions);
logicContains('SELECT Id FROM Items UNION ALL SELECT Id FROM Archive', $setExecution['sql'], 'Set-operation SQL failed.');
logicAssert($setExecution['params'] === [1, 2], 'Set-operation parameters were not merged.');

Response::setRequestContext([]);
$success = Response::successPayload(['executionTime' => 0.5, 'rowsReturned' => 1, 'data' => [['Id' => 1]]], 'Loaded');
logicAssert($success['success'] === true && $success['meta']['page'] === null && $success['meta']['rowsReturned'] === 1, 'Success response formatting failed.');
$validationError = Response::errorPayload('Invalid request.', 'INVALID_REQUEST', [['path' => 'fields', 'message' => 'Required.']]);
logicAssert($validationError['success'] === false && $validationError['error']['details'][0]['path'] === 'fields', 'Validation error formatting failed.');

echo "Backend logic tests passed.\n";
