<?php

require_once __DIR__ . '/../../core/QueryEngine.php';
require_once __DIR__ . '/../Resources/SqlResourceRegistry.php';
require_once __DIR__ . '/Query/PaginationBuilder.php';
require_once __DIR__ . '/../Requests/ApiRequestException.php';

class SqlRepository
{
    private QueryEngine $queryEngine;
    private SqlResourceRegistry $registry;
    private PaginationBuilder $paginationBuilder;
    private Logger $logger;

    public function __construct(
        ?QueryEngine $queryEngine = null,
        ?SqlResourceRegistry $registry = null,
        ?Logger $logger = null
    ) {
        $this->logger = $logger ?? new Logger();
        $this->queryEngine = $queryEngine ?? new QueryEngine(null, $this->logger);
        $this->registry = $registry ?? new SqlResourceRegistry();
        $this->paginationBuilder = new PaginationBuilder($this->queryEngine);
    }

    public function execute(array $request): array
    {
        $generationStarted = microtime(true);
        $definition = $this->registry->resolve($request['resource']);
        $sql = trim($this->queryEngine->getQuery($definition['file']));
        $sql = rtrim($sql, "; \t\n\r\0\x0B");
        if (!preg_match('/^SELECT\b/i', $sql)) {
            throw new RuntimeException('Approved SQL resources must be read-only queries.');
        }

        $allowedColumns = [];
        foreach ($definition['columns'] as $column) {
            $allowedColumns[strtolower($column)] = $column;
        }
        $allowedFilterColumns = [];
        foreach ($definition['filterColumns'] as $column) {
            $allowedFilterColumns[strtolower($column)] = $column;
        }
        $filterPlacement = $definition['filterPlacement'];
        if ($filterPlacement === 'source') {
            [$sourceWhereSql, $params] = $this->buildWhere(
                $request['filters'] ?? [],
                $request['filterLogic'] ?? 'AND',
                $allowedFilterColumns,
                null
            );
            $sql = str_replace(
                SqlResourceRegistry::RUNTIME_FILTER_MARKER,
                $sourceWhereSql,
                $sql
            );
            $whereSql = '';
        } else {
            [$whereSql, $params] = $this->buildWhere(
                $request['filters'] ?? [],
                $request['filterLogic'] ?? 'AND',
                $allowedFilterColumns
            );
        }
        $sort = !empty($request['sort'])
            ? $request['sort']
            : $definition['defaultSort'];
        $orderSql = $this->buildOrderBy($sort, $allowedColumns);
        $topLevelOrderBy = $this->findTopLevelOrderBy($sql);
        $countResourceSql = $topLevelOrderBy === null
            ? $sql
            : rtrim(substr($sql, 0, $topLevelOrderBy));
        $countBaseSql = $whereSql === ''
            ? $countResourceSql
            : "SELECT * FROM (\n{$countResourceSql}\n) AS SqlResource{$whereSql}";
        $topLimit = $this->getTopLimit($sql);
        $topFitsRequestedPage = $topLimit !== null
            && isset($request['pagination'])
            && $request['pagination']['page'] === 1
            && $request['pagination']['pageSize'] >= $topLimit;
        $canPageAuthoredSqlDirectly = $topLevelOrderBy !== null
            && empty($request['filters'])
            && empty($request['sort'])
            && ($topLimit === null || !isset($request['pagination']) || $topFitsRequestedPage);
        $canInferTotalRowsFromData = $canPageAuthoredSqlDirectly && $topFitsRequestedPage;

        if ($canPageAuthoredSqlDirectly) {
            $orderedSql = $sql;
        } else {
            $dataResourceSql = $topLevelOrderBy !== null && $topLimit !== null
                ? $sql
                : $countResourceSql;
            $dataBaseSql = "SELECT * FROM (\n{$dataResourceSql}\n) AS SqlResource{$whereSql}";
            $orderedSql = $dataBaseSql . $orderSql;
        }
        $paginationOrderSql = $this->buildOrderBy($sort, $allowedColumns, 'PagedSource');

        $paginationRequest = [];
        if (isset($request['pagination']) && !$canInferTotalRowsFromData) {
            $paginationRequest = [
                'page' => $request['pagination']['page'],
                'pageSize' => $request['pagination']['pageSize'],
            ];
        }
        $this->logger->timing('sql_generation', (microtime(true) - $generationStarted) * 1000, [
            'action' => 'sql',
            'resource' => $request['resource'],
            'page' => $request['pagination']['page'] ?? null,
            'pageSize' => $request['pagination']['pageSize'] ?? null,
        ]);
        $paged = $this->paginationBuilder->apply(
            $orderedSql,
            $countBaseSql,
            $params,
            $paginationRequest,
            $paginationOrderSql !== '' ? trim($paginationOrderSql) : null,
            !($canPageAuthoredSqlDirectly && $topFitsRequestedPage)
        );
        $result = $this->queryEngine->executePrepared($paged['sql'], $params, [
            'action' => 'sql',
            'resource' => $request['resource'],
            'queryPhase' => 'data',
            'page' => $request['pagination']['page'] ?? null,
            'pageSize' => $request['pagination']['pageSize'] ?? null,
        ]);
        if ($paged['totalRows'] !== null) {
            $result['totalRows'] = $paged['totalRows'];
        } elseif ($canInferTotalRowsFromData) {
            $result['totalRows'] = $result['rowsReturned'];
        }
        return $result;
    }

    private function getTopLimit(string $sql): ?int
    {
        if (preg_match(
            '/^SELECT\s+(?:DISTINCT\s+)?TOP\s*(?:\(\s*([0-9]+)\s*\)|([0-9]+))/i',
            $sql,
            $matches
        ) !== 1) {
            return null;
        }

        return (int)(($matches[1] ?? '') !== '' ? $matches[1] : ($matches[2] ?? ''));
    }

    private function findTopLevelOrderBy(string $sql): ?int
    {
        $length = strlen($sql);
        $depth = 0;
        $previousToken = null;
        $previousTokenStart = null;
        $orderByStart = null;

        for ($index = 0; $index < $length;) {
            $character = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($character === "'") {
                for ($index++; $index < $length; $index++) {
                    if ($sql[$index] !== "'") continue;
                    if ($index + 1 < $length && $sql[$index + 1] === "'") {
                        $index++;
                        continue;
                    }
                    $index++;
                    break;
                }
                continue;
            }
            if ($character === '"') {
                for ($index++; $index < $length; $index++) {
                    if ($sql[$index] !== '"') continue;
                    if ($index + 1 < $length && $sql[$index + 1] === '"') {
                        $index++;
                        continue;
                    }
                    $index++;
                    break;
                }
                continue;
            }
            if ($character === '[') {
                for ($index++; $index < $length; $index++) {
                    if ($sql[$index] !== ']') continue;
                    if ($index + 1 < $length && $sql[$index + 1] === ']') {
                        $index++;
                        continue;
                    }
                    $index++;
                    break;
                }
                continue;
            }
            if ($character === '-' && $next === '-') {
                $newline = strpos($sql, "\n", $index + 2);
                $index = $newline === false ? $length : $newline + 1;
                continue;
            }
            if ($character === '/' && $next === '*') {
                $commentEnd = strpos($sql, '*/', $index + 2);
                $index = $commentEnd === false ? $length : $commentEnd + 2;
                continue;
            }
            if ($character === '(') {
                $depth++;
                $index++;
                continue;
            }
            if ($character === ')') {
                $depth = max(0, $depth - 1);
                $index++;
                continue;
            }
            if ($depth === 0 && preg_match('/[A-Za-z_]/', $character) === 1) {
                $tokenStart = $index;
                while ($index < $length
                    && preg_match('/[A-Za-z0-9_]/', $sql[$index]) === 1) {
                    $index++;
                }
                $token = strtoupper(substr($sql, $tokenStart, $index - $tokenStart));
                if ($previousToken === 'ORDER' && $token === 'BY') {
                    $orderByStart = $previousTokenStart;
                }
                $previousToken = $token;
                $previousTokenStart = $tokenStart;
                continue;
            }

            $index++;
        }

        return $orderByStart;
    }

    private function buildWhere(
        array $filters,
        string $logic,
        array $allowedColumns,
        ?string $qualifier = 'SqlResource'
    ): array
    {
        if ($filters === []) return ['', []];
        $logic = strtoupper($logic);
        $conditions = [];
        $params = [];

        foreach ($filters as $filter) {
            $field = $this->requireAllowedColumn($filter['field'], $allowedColumns, 'filter');
            $operator = strtoupper($filter['operator']);
            $column = ($qualifier === null ? '' : $qualifier . '.') . '[' . $field . ']';
            if (in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
                $conditions[] = "{$column} {$operator}";
            } elseif (in_array($operator, ['IN', 'NOT IN'], true)) {
                $conditions[] = "{$column} {$operator} ("
                    . implode(', ', array_fill(0, count($filter['value']), '?')) . ')';
                array_push($params, ...$filter['value']);
            } elseif (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)) {
                $conditions[] = "{$column} {$operator} ? AND ?";
                array_push($params, ...$filter['value']);
            } else {
                $conditions[] = "{$column} {$operator} ?";
                $params[] = $filter['value'];
            }
        }

        return [' WHERE ' . implode(" {$logic} ", $conditions), $params];
    }

    private function buildOrderBy(
        array $sort,
        array $allowedColumns,
        string $qualifier = 'SqlResource'
    ): string
    {
        if ($sort === []) return '';
        $items = [];
        foreach ($sort as $entry) {
            $field = $this->requireAllowedColumn($entry['field'], $allowedColumns, 'sort');
            $direction = strtoupper($entry['direction'] ?? 'ASC');
            $items[] = $qualifier . '.[' . $field . '] ' . $direction;
        }
        return ' ORDER BY ' . implode(', ', $items);
    }

    private function requireAllowedColumn(string $field, array $allowedColumns, string $usage): string
    {
        $canonicalField = $allowedColumns[strtolower($field)] ?? null;
        if ($canonicalField === null) {
            throw new ApiRequestException(
                'Invalid SQL runtime field.',
                'INVALID_SQL_RUNTIME_FIELD',
                [['path' => $usage, 'message' => "Field is not exposed by this SQL resource: {$field}"]]
            );
        }
        return $canonicalField;
    }
}
