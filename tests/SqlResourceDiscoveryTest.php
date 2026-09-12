<?php

require_once __DIR__ . '/../app/Requests/QueryRequestValidator.php';
require_once __DIR__ . '/../app/Requests/QueryRequestNormalizer.php';
require_once __DIR__ . '/../app/Repositories/SqlRepository.php';

function discoveryAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function discoveryFailure(callable $operation, string $message): Throwable
{
    try {
        $operation();
    } catch (Throwable $exception) {
        return $exception;
    }
    throw new RuntimeException($message);
}

class SqlResourceDiscoveryEngine extends QueryEngine
{
    public array $executions = [];
    public function __construct() {}

    public function executePrepared($sql, array $params = [], array $context = [])
    {
        $this->executions[] = compact('sql', 'params', 'context');
        if (str_contains($sql, 'COUNT(*) AS TotalRows')) {
            return ['data' => [['TotalRows' => 4]]];
        }
        if (str_contains($sql, 'compatibility_level')) {
            return ['data' => [['CompatibilityLevel' => 150]]];
        }
        return [
            'executionTime' => 0.1,
            'rowsReturned' => 1,
            'data' => [['Item_Code' => 'A1']],
        ];
    }
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sql-resource-discovery-' . bin2hex(random_bytes(6));
$reports = $root . DIRECTORY_SEPARATOR . 'reports';
$nested = $root . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'sales';
$system = $root . DIRECTORY_SEPARATOR . 'system';
$files = [];

try {
    mkdir($reports, 0700, true);
    mkdir($nested, 0700, true);
    mkdir($system, 0700, true);
    $itemFile = $reports . DIRECTORY_SEPARATOR . 'item.sql';
    $customerFile = $reports . DIRECTORY_SEPARATOR . 'customer.sql';
    $topFile = $nested . DIRECTORY_SEPARATOR . 'top-products.sql';
    $textFile = $reports . DIRECTORY_SEPARATOR . 'not-sql.txt';
    $systemFile = $system . DIRECTORY_SEPARATOR . 'Tables.sql';
    file_put_contents($itemFile, "SELECT Item_Code, Item_Desc, Item_MRP FROM ItemMasterTable\n");
    file_put_contents($customerFile, "SELECT Cust_Name, COUNT(*) AS TotalCustomers FROM CustomerTable GROUP BY Cust_Name\n");
    file_put_contents($topFile, "SELECT TOP 10 Item_Code, Sales FROM Sales ORDER BY Sales DESC\n");
    file_put_contents($textFile, "SELECT Secret FROM Outside\n");
    file_put_contents($systemFile, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES\n");
    $files = [$itemFile, $customerFile, $topFile, $textFile, $systemFile];

    $registry = new SqlResourceRegistry([], $root);
    discoveryAssert(
        $registry->discoverResourceIds() === [
            'dashboard/sales/top-products',
            'reports/customer',
            'reports/item',
        ],
        'Recursive SQL resource discovery returned the wrong identities.'
    );
    $item = $registry->resolve('reports/item');
    discoveryAssert(
        $item['file'] === realpath($itemFile)
            && $item['columns'] === []
            && $item['defaultSort'] === [],
        'A discovered resource required execution metadata.'
    );
    discoveryAssert(
        $registry->resolve('item')['file'] === realpath($itemFile),
        'Unique basename compatibility resolution failed.'
    );

    $validator = new QueryRequestValidator();
    $normalizer = new QueryRequestNormalizer();
    foreach ([
        '../../secret',
        '../reports/item',
        '/reports/item',
        'C:/reports/item',
        'reports\\item',
        'reports/item.sql',
        "reports/item\0evil",
        'reports//item',
    ] as $invalidId) {
        $failure = discoveryFailure(
            fn () => $validator->validate(['action' => 'sql', 'resource' => $invalidId]),
            "Invalid resource identifier was accepted: {$invalidId}"
        );
        discoveryAssert(
            $failure instanceof ApiRequestException
                && $failure->getErrorCode() === 'INVALID_REQUEST',
            'Malformed resource identifier returned the wrong error.'
        );
    }
    foreach (['reports/missing', 'reports/not-sql', 'system/Tables'] as $unavailable) {
        $failure = discoveryFailure(
            fn () => $registry->resolve($unavailable),
            "Unavailable resource was accepted: {$unavailable}"
        );
        discoveryAssert(
            $failure instanceof ApiRequestException
                && $failure->getErrorCode() === 'INVALID_SQL_RESOURCE'
                && !str_contains($failure->getMessage(), $root),
            'Unavailable resource did not fail safely.'
        );
    }

    $simpleEngine = new SqlResourceDiscoveryEngine();
    (new SqlRepository($simpleEngine, $registry))->execute(['resource' => 'reports/item']);
    $simpleExecution = end($simpleEngine->executions);
    discoveryAssert(
        str_contains($simpleExecution['sql'], 'FROM ItemMasterTable')
            && $simpleExecution['params'] === [],
        'A simple unregistered SQL resource did not execute.'
    );

    $execution = [
        'columns' => ['Cust_Name', 'TotalCustomers'],
        'filters' => [
            'CustomerName' => [
                'expression' => 'Cust_Name',
                'placement' => 'source',
            ],
            'MinimumCustomers' => [
                'expression' => 'COUNT(*)',
                'placement' => 'having',
            ],
            'StartDate' => [
                'expression' => 'StDate',
                'placement' => 'source',
                'valueType' => 'integer-date',
            ],
        ],
        'defaultSort' => [['field' => 'Cust_Name', 'direction' => 'ASC']],
    ];
    $publicRequest = [
        'action' => 'sql',
        'resource' => 'reports/customer',
        'execution' => $execution,
        'filters' => [
            ['field' => 'CustomerName', 'operator' => 'LIKE', 'value' => 'A%'],
            ['field' => 'StartDate', 'operator' => 'BETWEEN', 'value' => ['2026-01-01', '2026-12-31']],
            ['field' => 'MinimumCustomers', 'operator' => '>=', 'value' => 2],
        ],
        'pagination' => ['page' => 1, 'pageSize' => 25],
    ];
    $validator->validate($publicRequest);
    $normalized = $normalizer->normalize($publicRequest);
    discoveryAssert($normalized['execution'] === $execution, 'Execution metadata was dropped by normalization.');
    $complexEngine = new SqlResourceDiscoveryEngine();
    $complexResult = (new SqlRepository($complexEngine, $registry))->execute($normalized);
    $complexData = end($complexEngine->executions);
    discoveryAssert(
        str_contains($complexData['sql'], 'WHERE (Cust_Name) LIKE ? AND (StDate) BETWEEN ? AND ?')
            && str_contains($complexData['sql'], 'HAVING (COUNT(*)) >= ?')
            && str_contains($complexData['sql'], 'ORDER BY SqlResource.[Cust_Name] ASC')
            && str_contains($complexData['sql'], 'OFFSET 0 ROWS')
            && $complexData['params'] === ['A%', 20260101, 20261231, 2]
            && $complexResult['totalRows'] === 4,
        'Execution metadata did not preserve mapped filters, conversion, sorting, or pagination.'
    );

    $nullEngine = new SqlResourceDiscoveryEngine();
    (new SqlRepository($nullEngine, $registry))->execute($normalizer->normalize([
        'action' => 'sql',
        'resource' => 'reports/item',
        'execution' => [
            'columns' => ['Item_Code', 'Item_Desc', 'Item_MRP'],
            'defaultSort' => [['field' => 'Item_Code', 'direction' => 'ASC']],
        ],
        'filters' => [['field' => 'Item_Desc', 'operator' => 'IS NULL']],
        'sort' => [['field' => 'Item_MRP', 'direction' => 'DESC']],
    ]));
    $nullExecution = end($nullEngine->executions);
    discoveryAssert(
        str_contains($nullExecution['sql'], 'SqlResource.[Item_Desc] IS NULL')
            && str_contains($nullExecution['sql'], 'SqlResource.[Item_MRP] DESC')
            && $nullExecution['params'] === [],
        'Derived output filter/sort metadata failed.'
    );

    foreach ([
        ['filters' => ['Bad' => ['expression' => 'BIL.Id; DROP TABLE Users', 'placement' => 'source']]],
        ['filters' => ['Bad' => ['expression' => 'BIL.Id OR 1=1', 'placement' => 'source']]],
        ['filters' => ['Bad' => ['expression' => 'SUM(BIL.Amount) OR 1=1', 'placement' => 'having']]],
        ['filters' => ['Bad' => ['expression' => 'BIL.Id', 'placement' => 'join']]],
        ['filters' => ['Bad' => ['expression' => 'BIL.Id', 'placement' => 'source', 'valueType' => 'sql']]],
        ['columns' => 'Item_Code', 'defaultSort' => [['field' => 'Item_Code', 'direction' => 'ASC']]],
    ] as $badExecution) {
        $failure = discoveryFailure(
            fn () => $validator->validate([
                'action' => 'sql',
                'resource' => 'reports/customer',
                'execution' => $badExecution,
            ]),
            'Unsafe SQL execution metadata was accepted.'
        );
        discoveryAssert($failure instanceof ApiRequestException, 'Unsafe execution metadata failed incorrectly.');
    }

    $badFilter = discoveryFailure(
        fn () => (new SqlRepository(new SqlResourceDiscoveryEngine(), $registry))->execute(
            $normalizer->normalize([
                'action' => 'sql',
                'resource' => 'reports/item',
                'execution' => ['columns' => ['Item_Code']],
                'filters' => [['field' => 'Secret', 'operator' => '=', 'value' => 1]],
            ])
        ),
        'Unknown runtime filter field was accepted.'
    );
    discoveryAssert(
        $badFilter instanceof ApiRequestException
            && $badFilter->getErrorCode() === 'INVALID_SQL_RUNTIME_FIELD',
        'Unknown runtime filter field returned the wrong error.'
    );
    $badSort = discoveryFailure(
        fn () => (new SqlRepository(new SqlResourceDiscoveryEngine(), $registry))->execute(
            $normalizer->normalize([
                'action' => 'sql',
                'resource' => 'reports/item',
                'execution' => ['columns' => ['Item_Code']],
                'sort' => [['field' => 'Secret', 'direction' => 'ASC']],
            ])
        ),
        'Unknown runtime sort field was accepted.'
    );
    discoveryAssert(
        $badSort instanceof ApiRequestException
            && $badSort->getErrorCode() === 'INVALID_SQL_RUNTIME_FIELD',
        'Unknown runtime sort field returned the wrong error.'
    );
    $unsortedPage = discoveryFailure(
        fn () => (new SqlRepository(new SqlResourceDiscoveryEngine(), $registry))->execute([
            'resource' => 'reports/item',
            'pagination' => ['page' => 1, 'pageSize' => 25],
        ]),
        'Pagination without an approved order was accepted.'
    );
    discoveryAssert(
        $unsortedPage instanceof ApiRequestException
            && $unsortedPage->getErrorCode() === 'INVALID_SQL_PAGINATION',
        'Unsorted pagination returned the wrong error.'
    );

    $topEngine = new SqlResourceDiscoveryEngine();
    $topResult = (new SqlRepository($topEngine, $registry))->execute([
        'resource' => 'dashboard/sales/top-products',
        'execution' => [
            'columns' => ['Item_Code', 'Sales'],
            'defaultSort' => [['field' => 'Sales', 'direction' => 'DESC']],
        ],
        'pagination' => ['page' => 1, 'pageSize' => 10],
    ]);
    discoveryAssert(
        count($topEngine->executions) === 1
            && !str_contains(end($topEngine->executions)['sql'], 'OFFSET 0 ROWS')
            && $topResult['totalRows'] === $topResult['rowsReturned'],
        'Discovered TOP first-page behavior regressed.'
    );

    $other = $root . DIRECTORY_SEPARATOR . 'archive';
    mkdir($other, 0700, true);
    $ambiguousItem = $other . DIRECTORY_SEPARATOR . 'item.sql';
    file_put_contents($ambiguousItem, 'SELECT Item_Code FROM ArchivedItems');
    $files[] = $ambiguousItem;
    $ambiguousFailure = discoveryFailure(
        fn () => (new SqlResourceRegistry([], $root))->resolve('item'),
        'Ambiguous basename resource was accepted.'
    );
    discoveryAssert(
        $ambiguousFailure instanceof ApiRequestException
            && $ambiguousFailure->getErrorCode() === 'INVALID_SQL_RESOURCE',
        'Ambiguous basename did not request a full resource identifier.'
    );

    $duplicateA = $reports . DIRECTORY_SEPARATOR . 'Duplicate.sql';
    $duplicateB = $reports . DIRECTORY_SEPARATOR . 'duplicate.sql';
    file_put_contents($duplicateA, 'SELECT 1 AS Value');
    file_put_contents($duplicateB, 'SELECT 2 AS Value');
    $files[] = $duplicateA;
    $files[] = $duplicateB;
    $duplicateFailure = discoveryFailure(
        fn () => (new SqlResourceRegistry([], $root))->discoverResourceIds(),
        'Case-insensitive duplicate resource identities were accepted.'
    );
    discoveryAssert(
        $duplicateFailure instanceof RuntimeException
            && str_contains($duplicateFailure->getMessage(), 'Duplicate SQL resource identity')
            && !str_contains($duplicateFailure->getMessage(), $root),
        'Duplicate resource identities did not fail safely.'
    );

    $realRegistry = new SqlResourceRegistry();
    $realIds = $realRegistry->discoverResourceIds();
    discoveryAssert(
        $realRegistry->resolve('reports/item')['file'] === realpath(QUERY_PATH . '/reports/item.sql')
            && $realRegistry->resolve('item')['file'] === realpath(QUERY_PATH . '/reports/item.sql')
            && in_array('reports/customer', $realIds, true)
            && in_array('widgets/item-dashboard-stats', $realIds, true),
        'Repository resource discovery or unique basename compatibility failed.'
    );
    foreach ($realIds as $realId) {
        discoveryAssert(
            !str_contains((string)file_get_contents($realRegistry->resolve($realId)['file']), '/*__RUNTIME_FILTERS__*/'),
            "Obsolete runtime-filter marker remains in {$realId}."
        );
    }

    echo "SQL resource discovery/execution metadata tests passed.\n";
} finally {
    foreach (array_reverse($files) as $file) {
        if (is_file($file)) @unlink($file);
    }
    @rmdir($system);
    @rmdir($nested);
    @rmdir(dirname($nested));
    @rmdir($root . DIRECTORY_SEPARATOR . 'archive');
    @rmdir($reports);
    @rmdir($root);
}
