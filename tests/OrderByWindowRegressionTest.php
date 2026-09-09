<?php

require_once __DIR__ . '/../app/Repositories/Query/SelectBuilder.php';

class OrderByTestQueryEngine extends QueryEngine
{
    private int $compatibilityLevel;

    public function __construct(int $compatibilityLevel)
    {
        $this->compatibilityLevel = $compatibilityLevel;
    }

    public function executePrepared($sql, array $params = [], array $context = [])
    {
        if (strpos($sql, 'compatibility_level') !== false) {
            return ['data' => [['CompatibilityLevel' => $this->compatibilityLevel]]];
        }
        return ['data' => [['TotalRows' => 20]]];
    }
}

class OrderByTestMetadataRepository extends MetadataRepository
{
    public array $validatedColumns = [];

    public function __construct() {}
    public function tableExists($table) { return true; }
    public function columnExists($table, $column)
    {
        $this->validatedColumns[] = [$table, $column];
        return !ctype_digit((string)$column);
    }
    public function getColumnDataType($table, $column) { return 'varchar'; }
    public function getColumns($tableName)
    {
        return ['data' => [
            ['COLUMN_NAME' => 'ItemCode'],
            ['COLUMN_NAME' => 'Description']
        ]];
    }
}

function assertContains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message . "\nMissing: {$needle}\nSQL: {$haystack}");
    }
}

function assertNotContains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) !== false) {
        throw new RuntimeException($message . "\nUnexpected: {$needle}\nSQL: {$haystack}");
    }
}

$metadata = new OrderByTestMetadataRepository();
$legacy = new SelectBuilder(new OrderByTestQueryEngine(100), $metadata);
$modern = new SelectBuilder(new OrderByTestQueryEngine(150), $metadata);
$base = ['table' => 'Items', 'columns' => ['ItemCode', 'Description']];

$legacyAsc = $legacy->build($base + [
    'sort' => [['column' => '1', 'direction' => 'ASC']],
    'page' => 1,
    'pageSize' => 10
]);
assertContains('ORDER BY [ItemCode] ASC', $legacyAsc['sql'], 'Legacy ASC order was not resolved.');
assertNotContains('ORDER BY 1 ASC', $legacyAsc['sql'], 'Legacy pagination retained integer ORDER BY.');

$legacyDesc = $legacy->build($base + [
    'sort' => [['column' => '1', 'direction' => 'DESC']],
    'page' => 1,
    'pageSize' => 10
]);
assertContains('ORDER BY [ItemCode] DESC', $legacyDesc['sql'], 'Legacy DESC order was not resolved.');

$legacyMultiple = $legacy->build($base + [
    'sort' => [
        ['column' => '1', 'direction' => 'ASC'],
        ['column' => '2', 'direction' => 'DESC']
    ],
    'page' => 1,
    'pageSize' => 10
]);
assertContains('ORDER BY [ItemCode] ASC, [Description] DESC', $legacyMultiple['sql'], 'Multiple legacy sort positions were not resolved.');

$legacyNamed = $legacy->build($base + [
    'sort' => [['column' => 'ItemCode', 'direction' => 'ASC']],
    'page' => 1,
    'pageSize' => 10
]);
assertContains('ORDER BY [ItemCode] ASC', $legacyNamed['sql'], 'Named legacy sort changed.');

$legacyAlias = $legacy->build([
    'table' => 'Items',
    'columns' => [['column' => 'Description', 'alias' => 'ItemName']],
    'sort' => [['column' => '1', 'direction' => 'ASC']],
    'page' => 1,
    'pageSize' => 10
]);
assertContains('ORDER BY [ItemName] ASC', $legacyAlias['sql'], 'Alias ordering changed.');

$legacyDefault = $legacy->build($base + ['page' => 1, 'pageSize' => 10]);
assertContains('ORDER BY [ItemCode] ASC', $legacyDefault['sql'], 'Default pagination ordering changed.');

$normal = $modern->build($base + [
    'sort' => [['column' => 'Description', 'direction' => 'DESC']]
]);
assertContains('ORDER BY Description DESC', $normal['sql'], 'Normal ORDER BY changed.');

$modernPaged = $modern->build($base + [
    'sort' => [['column' => 'ItemCode', 'direction' => 'ASC']],
    'page' => 2,
    'pageSize' => 10
]);
assertContains('ORDER BY ItemCode ASC', $modernPaged['sql'], 'Modern ORDER BY changed.');
assertContains('OFFSET 10 ROWS', $modernPaged['sql'], 'Modern pagination changed.');

$windowPosition = $modern->build([
    'table' => 'Items',
    'columns' => [
        'ItemCode',
        ['function' => 'ROW_NUMBER', 'orderBy' => [['column' => '1', 'direction' => 'ASC']]]
    ]
]);
assertContains('ROW_NUMBER() OVER (ORDER BY ItemCode ASC)', $windowPosition['sql'], 'Explicit window position was not resolved.');
assertNotContains('ROW_NUMBER() OVER (ORDER BY 1 ASC)', $windowPosition['sql'], 'Explicit window retained integer ORDER BY.');

$windowNamed = $modern->build([
    'table' => 'Items',
    'columns' => [
        'ItemCode',
        ['function' => 'ROW_NUMBER', 'orderBy' => [['column' => 'ItemCode', 'direction' => 'DESC']]]
    ]
]);
assertContains('ROW_NUMBER() OVER (ORDER BY ItemCode DESC)', $windowNamed['sql'], 'Named explicit window ordering changed.');

foreach ([['Items', 'ItemCode'], ['Items', 'Description']] as $validation) {
    if (!in_array($validation, $metadata->validatedColumns, true)) {
        throw new RuntimeException('Resolved ORDER BY column bypassed metadata validation.');
    }
}

echo "ORDER BY/window regression tests passed.\n";
