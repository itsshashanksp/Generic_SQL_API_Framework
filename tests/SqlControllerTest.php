<?php

require_once __DIR__ . '/../app/Requests/QueryRequestValidator.php';
require_once __DIR__ . '/../app/Requests/QueryRequestNormalizer.php';
require_once __DIR__ . '/../app/Repositories/SqlRepository.php';
require_once __DIR__ . '/../app/Controllers/SQLController.php';

function sqlAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function expectSqlRequestInvalid(QueryRequestValidator $validator, array $request): void
{
    try {
        $validator->validate($request);
    } catch (ApiRequestException $exception) {
        sqlAssert($exception->getErrorCode() === 'INVALID_REQUEST', 'Wrong SQL validation error code.');
        return;
    }
    throw new RuntimeException('Invalid SQL request was accepted.');
}

class SqlTestEngine extends QueryEngine
{
    public array $executions = [];
    public array $resultData = [['Item_Code' => 'A1']];
    public function __construct() {}
    public function executePrepared($sql, array $params = [], array $context = [])
    {
        $this->executions[] = ['sql' => $sql, 'params' => $params, 'context' => $context];
        if (str_contains($sql, 'COUNT(*) AS TotalRows')) return ['data' => [['TotalRows' => 7]]];
        if (str_contains($sql, 'compatibility_level')) return ['data' => [['CompatibilityLevel' => 150]]];
        return ['executionTime' => 0.5, 'rowsReturned' => count($this->resultData), 'data' => $this->resultData];
    }
}

class SqlTestRepository extends SqlRepository
{
    public function __construct() {}
    public function execute(array $request): array
    {
        return ['executionTime' => 0.1, 'rowsReturned' => 1, 'data' => [['ok' => 1]]];
    }
}

class SqlTestService extends SqlService
{
    public function __construct() {}
    public function execute(array $request): array
    {
        return (new SqlTestRepository())->execute($request);
    }
}

class SqlTestController extends SQLController
{
    public array $captured = [];
    protected function success($data = [], $message = 'Success', $code = 200)
    {
        $this->captured = compact('data', 'message', 'code');
    }
}

$validator = new QueryRequestValidator();
$normalizer = new QueryRequestNormalizer();
$publicRequest = [
    'action' => 'sql',
    'resource' => 'item',
    'filters' => [['field' => 'Item_Desc', 'operator' => 'LIKE', 'value' => "%O'Brien%"]],
    'sort' => [['field' => 'Item_Code', 'direction' => 'DESC']],
    'pagination' => ['page' => 2, 'pageSize' => 25],
];
$validator->validate($publicRequest);
$normalized = $normalizer->normalize($publicRequest);
sqlAssert($normalized['controller'] === 'SQL' && $normalized['action'] === 'execute', 'SQLController dispatch failed.');
sqlAssert($normalized['resource'] === 'item', 'SQL resource normalization failed.');

expectSqlRequestInvalid($validator, ['action' => 'sql', 'resource' => '../../secret']);
expectSqlRequestInvalid($validator, ['action' => 'sql', 'resource' => 'item', 'sql' => 'SELECT * FROM Users']);
expectSqlRequestInvalid($validator, ['action' => 'sql', 'resource' => 'item', 'sort' => [['field' => 'Id', 'direction' => 'DROP']]]);

$registry = new SqlResourceRegistry();
$statsDefinition = $registry->resolve('item-dashboard-stats');
sqlAssert(
    $statsDefinition['columns'] === ['TotalItems', 'MinimumSP', 'MaximumSP', 'TotalValue'],
    'Statistic output columns changed.'
);
sqlAssert(
    $statsDefinition['filterColumns'] === ['Item_Desc', 'Std_Vat']
        && $statsDefinition['filterPlacement'] === 'source',
    'Statistic source filter metadata was not loaded.'
);
try {
    $registry->resolve('not-approved');
    throw new RuntimeException('Unknown SQL resource was accepted.');
} catch (ApiRequestException $exception) {
    sqlAssert($exception->getErrorCode() === 'INVALID_SQL_RESOURCE', 'Wrong resource error code.');
}

try {
    (new SqlResourceRegistry(['escaped' => [
        'file' => __FILE__,
        'columns' => ['Item_Code'],
        'defaultSort' => [['field' => 'Item_Code', 'direction' => 'ASC']],
    ]]))->resolve('escaped');
    throw new RuntimeException('A registered path outside the query root was accepted.');
} catch (RuntimeException $exception) {
    sqlAssert(
        str_contains($exception->getMessage(), 'unavailable'),
        'Registry path containment did not reject the external file.'
    );
}

$engine = new SqlTestEngine();
$repository = new SqlRepository($engine, $registry);
$result = $repository->execute($normalized);
sqlAssert($result['totalRows'] === 7, 'SQL pagination total was not preserved.');
$execution = end($engine->executions);
sqlAssert(str_contains($execution['sql'], 'FROM ('), 'Approved SQL was not wrapped as a resource query.');
sqlAssert(str_contains($execution['sql'], 'SqlResource.[Item_Desc] LIKE ?'), 'Runtime filter was not applied.');
sqlAssert(str_contains($execution['sql'], 'SqlResource.[Item_Code] DESC'), 'Runtime sort was not applied.');
sqlAssert(str_contains($execution['sql'], 'OFFSET 25 ROWS'), 'Runtime pagination was not applied.');
sqlAssert($execution['params'] === ["%O'Brien%"], 'Runtime filter value was not parameterized.');
sqlAssert(!str_contains($execution['sql'], "O'Brien"), 'Runtime value leaked into SQL text.');
sqlAssert($execution['context']['queryPhase'] === 'data', 'Main query phase was not identified.');
$countExecution = array_values(array_filter(
    $engine->executions,
    fn (array $entry): bool => str_contains($entry['sql'], 'COUNT(*) AS TotalRows')
))[0];
sqlAssert($countExecution['context']['queryPhase'] === 'pagination_count', 'Count query phase was not identified.');
sqlAssert($countExecution['params'] === $execution['params'], 'Count and data query parameters diverged.');

$emptySortRequest = $normalizer->normalize([
    'action' => 'sql',
    'resource' => 'item',
    'filters' => [],
    'sort' => [],
    'pagination' => ['page' => 1, 'pageSize' => 25],
]);
$emptySortEngine = new SqlTestEngine();
$emptySortRepository = new SqlRepository($emptySortEngine, $registry);
$emptySortRepository->execute($emptySortRequest);
$emptySortExecution = end($emptySortEngine->executions);
sqlAssert(
    str_contains($emptySortExecution['sql'], 'ORDER BY SqlResource.[Item_Code] ASC'),
    'An empty runtime sort did not use the registered resource default sort.'
);
sqlAssert(
    strpos($emptySortExecution['sql'], 'ORDER BY') < strpos($emptySortExecution['sql'], 'OFFSET 0 ROWS'),
    'SQL Server pagination was generated without ORDER BY before OFFSET.'
);
sqlAssert(
    str_contains($emptySortExecution['sql'], 'FETCH NEXT 25 ROWS ONLY'),
    'Empty-sort regression request did not retain pagination.'
);

$groupedRequest = $normalizer->normalize([
    'action' => 'sql',
    'resource' => 'bill-category-sales-month-wise',
    'filters' => [],
    'sort' => [],
    'pagination' => ['page' => 1, 'pageSize' => 100],
]);
$groupedEngine = new SqlTestEngine();
$groupedRepository = new SqlRepository($groupedEngine, $registry);
$groupedResult = $groupedRepository->execute($groupedRequest);
$groupedCountExecutions = array_values(array_filter(
    $groupedEngine->executions,
    fn (array $entry): bool => str_contains($entry['sql'], 'COUNT(*) AS TotalRows')
));
$groupedCountSql = $groupedCountExecutions[0]['sql'];
$groupedDataExecution = end($groupedEngine->executions);
sqlAssert(
    str_contains($groupedCountSql, 'GROUP BY') && str_contains($groupedCountSql, 'HAVING'),
    'Grouped count source lost GROUP BY or HAVING.'
);
sqlAssert(
    !str_contains($groupedCountSql, 'ORDER BY Month, Category'),
    'Authored ORDER BY leaked into the grouped count query.'
);
sqlAssert(
    !str_contains($groupedCountSql, ') AS SqlResource'),
    'Unfiltered grouped count retained a redundant resource wrapper.'
);
sqlAssert(
    str_contains($groupedDataExecution['sql'], 'ORDER BY Month, Category'),
    'Authored ORDER BY was not preserved in the data query.'
);
sqlAssert(
    !str_contains($groupedDataExecution['sql'], ') AS SqlResource')
        && substr_count($groupedDataExecution['sql'], 'OFFSET 0 ROWS') === 1,
    'Grouped authored SQL was not paginated directly at its top level.'
);
sqlAssert($groupedResult['rowsReturned'] === 1, 'Grouped SQL-mode data result was lost.');
sqlAssert($groupedResult['totalRows'] === 7, 'Grouped SQL-mode count result was lost.');

foreach (['bill-sales-month-wise', 'bill-purchases-month-wise'] as $orderedResource) {
    $orderedEngine = new SqlTestEngine();
    (new SqlRepository($orderedEngine, $registry))->execute($normalizer->normalize([
        'action' => 'sql',
        'resource' => $orderedResource,
        'filters' => [],
        'sort' => [],
        'pagination' => ['page' => 1, 'pageSize' => 12],
    ]));
    $orderedCountExecutions = array_values(array_filter(
        $orderedEngine->executions,
        fn (array $entry): bool => str_contains($entry['sql'], 'COUNT(*) AS TotalRows')
    ));
    sqlAssert(
        !str_contains($orderedCountExecutions[0]['sql'], 'ORDER BY'),
        "Authored ordering leaked into the {$orderedResource} count query."
    );
    $orderedDataExecution = end($orderedEngine->executions);
    sqlAssert(
        str_contains($orderedDataExecution['sql'], "ORDER BY")
            && !str_contains($orderedDataExecution['sql'], ') AS SqlResource')
            && substr_count($orderedDataExecution['sql'], 'OFFSET 0 ROWS') === 1,
        "{$orderedResource} was not paginated directly after its authored ordering."
    );
}

$topRequest = $normalizer->normalize([
    'action' => 'sql',
    'resource' => 'bill-top-10-categories',
    'filters' => [],
    'sort' => [],
    'pagination' => ['page' => 1, 'pageSize' => 10],
]);
$topEngine = new SqlTestEngine();
$topResult = (new SqlRepository($topEngine, $registry))->execute($topRequest);
$topCountExecutions = array_values(array_filter(
    $topEngine->executions,
    fn (array $entry): bool => str_contains($entry['sql'], 'COUNT(*) AS TotalRows')
));
$topDataExecution = end($topEngine->executions);
sqlAssert($topCountExecutions === [], 'A complete first-page TOP resource executed an unnecessary count query.');
sqlAssert(count($topEngine->executions) === 1, 'A complete first-page TOP resource executed more than its data query.');
sqlAssert($topResult['totalRows'] === $topResult['rowsReturned'], 'TOP resource total rows were not inferred from its complete result.');
sqlAssert(str_contains($topDataExecution['sql'], 'ORDER BY Sales DESC'), 'TOP resource data ordering was removed.');
sqlAssert(
    !str_contains($topDataExecution['sql'], ') AS SqlResource')
        && !str_contains($topDataExecution['sql'], 'OFFSET 0 ROWS'),
    'A first-page TOP resource was unnecessarily wrapped or repaginated.'
);

$partialTopEngine = new SqlTestEngine();
(new SqlRepository($partialTopEngine, $registry))->execute($normalizer->normalize([
    'action' => 'sql',
    'resource' => 'bill-top-10-categories',
    'filters' => [],
    'sort' => [],
    'pagination' => ['page' => 1, 'pageSize' => 5],
]));
$partialTopCountExecutions = array_values(array_filter(
    $partialTopEngine->executions,
    fn (array $entry): bool => str_contains($entry['sql'], 'COUNT(*) AS TotalRows')
));
sqlAssert(
    count($partialTopCountExecutions) === 1,
    'A partially displayed TOP resource skipped the count required by pagination.'
);
$partialTopDataExecution = end($partialTopEngine->executions);
sqlAssert(
    str_contains($partialTopDataExecution['sql'], 'FETCH NEXT 5 ROWS ONLY'),
    'A partially displayed TOP resource lost data pagination.'
);

$customerEngine = new SqlTestEngine();
(new SqlRepository($customerEngine, $registry))->execute($normalizer->normalize([
    'action' => 'sql',
    'resource' => 'customer',
    'filters' => [],
    'sort' => [],
    'pagination' => ['page' => 1, 'pageSize' => 25],
]));
$customerExecution = end($customerEngine->executions);
sqlAssert(str_contains($customerExecution['sql'], 'FROM CustomerTable'), 'Customer SQL resource stopped loading.');
sqlAssert(str_contains($customerExecution['sql'], 'GROUP BY Cust_Name'), 'Customer SQL grouping was changed.');

$statFilterCases = [
    'Item_Desc only' => [
        [['field' => 'Item_Desc', 'operator' => 'LIKE', 'value' => '%ABC%']],
        ['%ABC%'],
    ],
    'Std_Vat only' => [
        [['field' => 'Std_Vat', 'operator' => 'LIKE', 'value' => '%5%']],
        ['%5%'],
    ],
    'Item_Desc and Std_Vat' => [
        [
            ['field' => 'Item_Desc', 'operator' => 'LIKE', 'value' => '%ABC%'],
            ['field' => 'Std_Vat', 'operator' => 'LIKE', 'value' => '%5%'],
        ],
        ['%ABC%', '%5%'],
    ],
];
foreach ($statFilterCases as $label => [$filters, $expectedParams]) {
    $statsEngine = new SqlTestEngine();
    $statsEngine->resultData = [[
        'TotalItems' => 2,
        'MinimumSP' => 10,
        'MaximumSP' => 20,
        'TotalValue' => 30,
    ]];
    $statsResult = (new SqlRepository($statsEngine, $registry))->execute(
        $normalizer->normalize([
            'action' => 'sql',
            'resource' => 'item-dashboard-stats',
            'filters' => $filters,
        ])
    );
    $statsExecution = end($statsEngine->executions);
    sqlAssert(
        str_contains($statsExecution['sql'], 'FROM ItemMasterTable')
            && str_contains($statsExecution['sql'], '[Item_Desc]') === ($label !== 'Std_Vat only')
            && str_contains($statsExecution['sql'], '[Std_Vat]') === ($label !== 'Item_Desc only'),
        "{$label} was not applied to the statistic source."
    );
    sqlAssert(
        !str_contains($statsExecution['sql'], 'SqlResource.[Item_Desc]')
            && !str_contains($statsExecution['sql'], 'SqlResource.[Std_Vat]')
            && !str_contains($statsExecution['sql'], SqlResourceRegistry::RUNTIME_FILTER_MARKER),
        "{$label} was applied after aggregation or left an unresolved marker."
    );
    sqlAssert($statsExecution['params'] === $expectedParams, "{$label} parameters changed.");
    sqlAssert(
        $statsResult['data'][0] === [
            'TotalItems' => 2,
            'MinimumSP' => 10,
            'MaximumSP' => 20,
            'TotalValue' => 30,
        ],
        "{$label} statistic response mapping changed."
    );
}

$unfilteredStatsEngine = new SqlTestEngine();
$unfilteredStatsEngine->resultData = [[
    'TotalItems' => 0,
    'MinimumSP' => null,
    'MaximumSP' => null,
    'TotalValue' => null,
]];
$unfilteredStatsResult = (new SqlRepository($unfilteredStatsEngine, $registry))->execute(
    $normalizer->normalize([
        'action' => 'sql',
        'resource' => 'item-dashboard-stats',
        'filters' => [],
    ])
);
$unfilteredStatsExecution = end($unfilteredStatsEngine->executions);
sqlAssert(
    !str_contains($unfilteredStatsExecution['sql'], SqlResourceRegistry::RUNTIME_FILTER_MARKER)
        && !str_contains($unfilteredStatsExecution['sql'], ' WHERE '),
    'An empty statistic filter produced a predicate or unresolved marker.'
);
sqlAssert(
    $unfilteredStatsResult['data'][0]['TotalItems'] === 0
        && $unfilteredStatsResult['data'][0]['MinimumSP'] === null
        && $unfilteredStatsResult['data'][0]['MaximumSP'] === null
        && $unfilteredStatsResult['data'][0]['TotalValue'] === null,
    'Zero-row statistic response values changed.'
);

$tableFilterEngine = new SqlTestEngine();
(new SqlRepository($tableFilterEngine, $registry))->execute($normalizer->normalize([
    'action' => 'sql',
    'resource' => 'item-dashboard-table',
    'filters' => [
        ['field' => 'Item_Desc', 'operator' => 'LIKE', 'value' => '%ABC%'],
        ['field' => 'Std_Vat', 'operator' => 'LIKE', 'value' => '%5%'],
    ],
    'sort' => [['field' => 'Item_Code', 'direction' => 'DESC']],
    'pagination' => ['page' => 2, 'pageSize' => 10],
]));
$tableFilterExecution = end($tableFilterEngine->executions);
sqlAssert(
    str_contains($tableFilterExecution['sql'], 'SqlResource.[Item_Desc] LIKE ?')
        && str_contains($tableFilterExecution['sql'], 'SqlResource.[Std_Vat] LIKE ?')
        && str_contains($tableFilterExecution['sql'], 'SqlResource.[Item_Code] DESC')
        && str_contains($tableFilterExecution['sql'], 'OFFSET 10 ROWS'),
    'Item dashboard table filtering, sorting, or pagination changed.'
);

try {
    $repository->execute(['resource' => 'item', 'filters' => [[
        'field' => 'Password', 'operator' => '=', 'value' => 'secret'
    ]]]);
    throw new RuntimeException('Non-allowlisted runtime column was accepted.');
} catch (ApiRequestException $exception) {
    sqlAssert($exception->getErrorCode() === 'INVALID_SQL_RUNTIME_FIELD', 'Wrong runtime field error code.');
}

try {
    (new SqlRepository(new SqlTestEngine(), $registry))->execute([
        'resource' => 'item-dashboard-stats',
        'filters' => [['field' => 'Item_Code', 'operator' => '=', 'value' => 'A1']],
    ]);
    throw new RuntimeException('Unallowlisted statistic source field was accepted.');
} catch (ApiRequestException $exception) {
    sqlAssert(
        $exception->getErrorCode() === 'INVALID_SQL_RUNTIME_FIELD',
        'Wrong statistic runtime field error code.'
    );
}

$controller = new SqlTestController(new SqlTestService());
$controller->execute($normalized);
sqlAssert($controller->captured['message'] === 'Data Loaded Successfully', 'SQLController response message changed.');
sqlAssert($controller->captured['data']['data'][0]['ok'] === 1, 'SQLController did not return service data.');

Response::setRequestContext($publicRequest);
$response = Response::successPayload($controller->captured['data'], $controller->captured['message']);
sqlAssert($response['success'] === true, 'SQL response was not standardized.');
sqlAssert($response['meta']['page'] === 2 && $response['meta']['pageSize'] === 25, 'SQL response pagination metadata changed.');

echo "SQL controller tests passed.\n";
