<?php

require_once __DIR__ . '/../../core/QueryEngine.php';
require_once __DIR__ . '/MetadataRepository.php';

class QueryRepository
{
    private QueryEngine $queryEngine;
    private MetadataRepository $metadataRepository;

    // Table & Alias Map
    private array $tableMap = [];

    public function __construct()
    {
        $this->queryEngine = new QueryEngine();
        $this->metadataRepository = new MetadataRepository();
    }
    
    private function resolveColumn(string $column): array
    {
        // Normal column (Bill_No)
        if (strpos($column, '.') === false) {
            return [
            'table'  => null,
            'column' => $column
        ];
    }

    // Qualified column (B.Bill_No)
    [$alias, $columnName] = explode('.', $column, 2);

        if (!isset($this->tableMap[$alias])) {
            throw new Exception(
            "Unknown table alias: {$alias}"
        );
    }

    return [
        'table'  => $this->tableMap[$alias],
        'column' => $columnName
    ];
    }

    public function select($request)
    {
        /*
         * Validate Table
         */
        if (
            !$this->metadataRepository->tableExists(
                $request['table']
            )
        ) {
            throw new Exception("Invalid table name.");
        }

        /*
        * Register Base Table
        */
        $this->tableMap = [];

        $this->tableMap[$request['table']] = $request['table'];

        if (!empty($request['alias'])) {
            $this->tableMap[$request['alias']] = $request['table'];
        }

        /*
        * Pre-register JOIN Tables & Aliases
        */
        if (!empty($request['joins'])) {

            foreach ($request['joins'] as $join) {

            $this->tableMap[$join['table']] = $join['table'];

        if (!empty($join['alias'])) {
            $this->tableMap[$join['alias']] = $join['table'];
        }
       }
      }

        /*
        * Validate Select Columns
        */
        foreach ($request['columns'] as $column) {

        if (is_array($column)) {

/*
 * Aggregate Column
 */
if (isset($column['function'])) {

    /*
     * GETDATE() does not require a column.
     */
    if (
        strtoupper($column['function']) != "GETDATE"
    ) {

        if (empty($column['column'])) {
            throw new Exception(
                "Function {$column['function']} requires a column."
            );
        }

        if (strtoupper($column['column']) != "*") {

            $resolved =
                $this->resolveColumn(
                    $column['column']
                );

            $table =
                $resolved['table']
                ?? $request['table'];

            if (
                !$this->metadataRepository->columnExists(
                    $table,
                    $resolved['column']
                )
            ) {
                throw new Exception(
                    "Invalid column: {$column['column']}"
                );
            }
        }
    }
}

        /*
         * Normal Column With Alias
         */
        else {

            $resolved = $this->resolveColumn($column['column']);

            $table = $resolved['table'] ?? $request['table'];

            if (
                !$this->metadataRepository->columnExists(
                    $table,
                    $resolved['column']
                )
            ) {
                throw new Exception(
                    "Invalid column: {$column['column']}"
                );
            }
        }

    } else {

        $resolved = $this->resolveColumn($column);

        $table = $resolved['table'] ?? $request['table'];

        if (
            !$this->metadataRepository->columnExists(
                $table,
                $resolved['column']
            )
        ) {
            throw new Exception(
                "Invalid column: {$column}"
            );
        }
    }
        }

    /*
     * Build SELECT Columns
     */
    $selectColumns = [];

    foreach ($request['columns'] as $column) {

        /*
         * Plain column
         */
        if (is_string($column)) {

            $selectColumns[] = $column;
            continue;
        }

/*
 * Aggregate / String / Date Function
 */
if (isset($column['function'])) {

    $aggregateFunctions = [
        "COUNT",
        "SUM",
        "AVG",
        "MIN",
        "MAX"
    ];

    $stringFunctions = [
        "UPPER",
        "LOWER",
        "LTRIM",
        "RTRIM",
        "TRIM",
        "LEN"
    ];

    $dateFunctions = [
        "YEAR",
        "MONTH",
        "DAY",
        "DATEPART",
        "DATENAME",
        "GETDATE"
    ];

    $mathFunctions = [
        "ABS",
        "ROUND",
        "CEILING",
        "FLOOR",
        "POWER",
        "SQRT",
        "EXP",
        "LOG"
    ];

    $function =
        strtoupper($column['function']);

    if (
        !in_array($function,$aggregateFunctions)
        &&
        !in_array($function,$stringFunctions)
        &&
        !in_array($function,$dateFunctions)
        &&
        !in_array($function, $mathFunctions)
    ) {
        throw new Exception(
            "Invalid SQL function: {$column['function']}"
        );
    }

    $alias =
        $column['alias']
        ?? strtolower($column['function']);

/*
 * Resolve column for functions that require one
 */
$resolvedColumn = null;

if (isset($column['column'])) {

    $resolved =
        $this->resolveColumn(
            $column['column']
        );

    if (!empty($resolved['table'])) {
        $resolvedColumn =
            $resolved['table']
            . "."
            . $resolved['column'];
    } else {
        $resolvedColumn =
            $resolved['column'];
    }
}

    /*
     * GETDATE()
     */
    if ($function == "GETDATE") {

        $selectColumns[] =
            "GETDATE() AS [{$alias}]";

        continue;
    }

/*
 * DATEPART() / DATENAME()
 */
if (
    $function == "DATEPART"
    ||
    $function == "DATENAME"
) {

    if (empty($column['part'])) {
        throw new Exception(
            "{$function} requires 'part'."
        );
    }

    $dateColumn =
        "CONVERT(date, CAST("
        . $column['column']
        . " AS varchar(8)), 112)";

    $selectColumns[] =
        $function
        . "("
        . strtoupper($column['part'])
        . ", "
        . $dateColumn
        . ") AS ["
        . $alias
        . "]";

    continue;
}

/*
 * YEAR() / MONTH() / DAY()
 */
if (
    in_array(
        $function,
        [
            "YEAR",
            "MONTH",
            "DAY"
        ]
    )
) {

    $dateColumn =
        "CONVERT(date, CAST("
        . $column['column']
        . " AS varchar(8)), 112)";

    $selectColumns[] =
        $function
        . "("
        . $dateColumn
        . ") AS ["
        . $alias
        . "]";

    continue;
}

/*
 * Mathematical Functions
 */
if (
    in_array(
        $function,
        [
            "ABS",
            "CEILING",
            "FLOOR",
            "SQRT",
            "EXP",
            "LOG"
        ]
    )
) {

    $selectColumns[] =
        $function
        . "("
        . $resolvedColumn
        . ") AS ["
        . $alias
        . "]";

    continue;
}

/*
 * ROUND()
 */
if ($function == "ROUND") {

    $precision =
        $column['precision']
        ?? 0;

    $selectColumns[] =
        "ROUND("
        . $resolvedColumn
        . ", "
        . (int)$precision
        . ") AS ["
        . $alias
        . "]";

    continue;
}

/*
 * POWER()
 */
if ($function == "POWER") {

    if (!isset($column['power'])) {
        throw new Exception(
            "POWER requires 'power'."
        );
    }

    $selectColumns[] =
        "POWER("
        . $resolvedColumn
        . ", "
        . (float)$column['power']
        . ") AS ["
        . $alias
        . "]";

    continue;
}

    /*
     * Aggregate & String Functions
     */
    $selectColumns[] =
        $function
        . "("
        . $column['column']
        . ") AS ["
        . $alias
        . "]";

    continue;
}

        /*
         * Normal Column With Alias
         */
        $sqlColumn = $column['column'];

        if (!empty($column['alias'])) {
            $sqlColumn .= " AS [" . $column['alias'] . "]";
        }

        $selectColumns[] = $sqlColumn;
    }

    $columns = implode(", ", $selectColumns);

    $distinct = "";

    if (!empty($request['distinct'])) {
        $distinct = "DISTINCT";
    }

    $top = "";

    if (!empty($request['top'])) {

        if (
            !is_numeric($request['top']) ||
            $request['top'] <= 0
        ) {
            throw new Exception(
                "TOP must be a positive number."
            );
        }

        $top = "TOP " . (int)$request['top'];
    }

    $baseAlias = "";

    if (!empty($request['alias'])) {
        $baseAlias = " " . $request['alias'];
    }

    $sql = "
        SELECT
        {$distinct} {$top} {$columns}
    FROM
        {$request['table']}{$baseAlias}
    ";

        $params = [];

/*
 * JOIN
 */
if (!empty($request['joins'])) {

    foreach ($request['joins'] as $join) {

        $allowedTypes = [
            "INNER",
            "LEFT",
            "RIGHT"
        ];

        $type = strtoupper($join['type']);

        if (!in_array($type, $allowedTypes)) {
            throw new Exception(
                "Invalid JOIN type: {$type}"
            );
        }

        if (
            !$this->metadataRepository->tableExists(
                $join['table']
            )
        ) {
            throw new Exception(
                "Invalid JOIN table: {$join['table']}"
            );
        }

        $alias = "";

        if (!empty($join['alias'])) {
            $alias = " " . $join['alias'];
        }

        $sql .= "
            {$type} JOIN {$join['table']}{$alias}
            ON {$join['left']} = {$join['right']}
        ";
    }
}

        /*
         * WHERE
         */
        if (!empty($request['filters'])) {

            foreach ($request['filters'] as $filter) {

                $resolved = $this->resolveColumn($filter['column']);
                $table = $resolved['table'] ?? $request['table'];

                if (
                    !$this->metadataRepository->columnExists(
                        $table,
                        $resolved['column']
                    )
                ) {
                    throw new Exception(
                        "Invalid filter column: {$filter['column']}"
                    );
                }
            }

            $conditions = [];

            foreach ($request['filters'] as $filter) {

                $allowedOperators = [
                    "=",
                    "!=",
                    "<>",
                    ">",
                    "<",
                    ">=",
                    "<=",
                    "LIKE",
                    "NOT LIKE",
                    "IN",
                    "NOT IN",
                    "BETWEEN",
                    "NOT BETWEEN",
                    "IS NULL",
                    "IS NOT NULL"
                ];

                if (
                    !in_array(
                        strtoupper($filter['operator']),
                        $allowedOperators
                    )
                ) {
                    throw new Exception(
                        "Invalid operator: {$filter['operator']}"
                    );
                }

                if (
                    strtoupper($filter['operator']) == "IS NULL"
                    || strtoupper($filter['operator']) == "IS NOT NULL"
                ) {

                    $conditions[] =
                        "{$filter['column']} {$filter['operator']}";

                    continue;
                }

                if (
                    strtoupper($filter['operator']) == "IN"
                    || strtoupper($filter['operator']) == "NOT IN"
                ) {

                if (
                    !is_array($filter['value']) ||
                    empty($filter['value'])
                ) {
                    throw new Exception(
                        "{$filter['operator']} requires at least one value."
                    );
                }

                $placeholders = implode(
                    ", ",
                    array_fill(0, count($filter['value']), "?")
                );

                $conditions[] =
                    "{$filter['column']} {$filter['operator']} ({$placeholders})";

                foreach ($filter['value'] as $value) {
                    $params[] = $value;
                }

                continue;
            }

            if (
                strtoupper($filter['operator']) == "BETWEEN"
                || strtoupper($filter['operator']) == "NOT BETWEEN"
            ) {

            if (
                !is_array($filter['value']) ||
                count($filter['value']) != 2
            ) {
                throw new Exception(
                    "{$filter['operator']} requires exactly two values."
              );
            }

            $conditions[] =
                 "{$filter['column']} {$filter['operator']} ? AND ?";

            $params[] = $filter['value'][0];
            $params[] = $filter['value'][1];

            continue;
     }

     $conditions[] =
          "{$filter['column']} {$filter['operator']} ?";

    $params[] = $filter['value'];

            }

        $conditionType = "AND";

        if (!empty($request['condition'])) {

            $conditionType = strtoupper($request['condition']);

        if (!in_array($conditionType, ["AND", "OR"])) {
            throw new Exception(
                "Condition must be AND or OR."
            );
        }
    }

    $sql .= " WHERE " . implode(" {$conditionType} ", $conditions);

        }

        /*
        * GROUP BY
        */
        if (!empty($request['groupBy'])) {

            foreach ($request['groupBy'] as $column) {

                $resolved = $this->resolveColumn($column);
                $table = $resolved['table'] ?? $request['table'];

        if (
            !$this->metadataRepository->columnExists(
                $table,
                $resolved['column']
            )
        ) {
            throw new Exception(
                "Invalid GROUP BY column: {$column}"
            );
        }
    }

    $sql .= " GROUP BY " . implode(
        ", ",
        $request['groupBy']
    );
    }

        /*
        * HAVING
        */
        if (!empty($request['having'])) {

        $havingConditions = [];

        foreach ($request['having'] as $having) {

        $aggregateFunctions = [
             "COUNT",
             "SUM",
             "AVG",
             "MIN",
             "MAX"
        ];

        $stringFunctions = [
            "UPPER",
            "LOWER",
            "LTRIM",
            "RTRIM",
            "TRIM",
            "LEN"
       ];

        if (
            !in_array(
                strtoupper($having['function']),
                $aggregateFunctions
            )
        ) {
            throw new Exception(
                "Invalid HAVING function: {$having['function']}"
            );
        }

        if (strtoupper($having['column']) != "*") {

            $resolved = $this->resolveColumn($having['column']);

            $table = $resolved['table'] ?? $request['table'];

        if (
            !$this->metadataRepository->columnExists(
            $table,
            $resolved['column']
            )
        ) {
            throw new Exception(
                "Invalid HAVING column: {$having['column']}"
            );
        }
    }

        $allowedOperators = [
            "=",
            "!=",
            "<>",
            ">",
            "<",
            ">=",
            "<="
        ];

        if (
            !in_array(
                strtoupper($having['operator']),
                $allowedOperators
            )
        ) {
            throw new Exception(
                "Invalid HAVING operator: {$having['operator']}"
            );
        }

        $havingConditions[] =
            strtoupper($having['function'])
            . "("
            . $having['column']
            . ") "
            . $having['operator']
            . " ?";

        $params[] =
            $having['value'];
    }

    $sql .=
        " HAVING "
        . implode(" AND ", $havingConditions);
    }

     /*
     * ORDER BY
     */
    if (!empty($request['sort'])) {

        $allowedDirections = [
            "ASC",
            "DESC"
        ];

        $orders = [];

        foreach ($request['sort'] as $sort) {

        $isAlias = false;

    foreach ($request['columns'] as $selectedColumn) {

        if (
            is_array($selectedColumn)
            && !empty($selectedColumn['alias'])
            && $selectedColumn['alias'] === $sort['column']
        ) {
            $isAlias = true;
            break;
        }
    }

    if (!$isAlias) {

        $resolved = $this->resolveColumn($sort['column']);

        $table = $resolved['table'] ?? $request['table'];

        if (
            !$this->metadataRepository->columnExists(
                $table,
                $resolved['column']
            )
        ) {
            throw new Exception(
                "Invalid ORDER BY column: {$sort['column']}"
            );
        }
    }

        if (
            !in_array(
                strtoupper($sort['direction']),
                $allowedDirections
            )
        ) {
            throw new Exception(
                "Invalid sort direction: {$sort['direction']}"
           );
        }

        $orderColumn = $sort['column'];

    if ($isAlias) {
        $orderColumn = "[" . $orderColumn . "]";
    }

        $orders[] =
            $orderColumn . " "
            . strtoupper($sort['direction']);
    }

        $sql .= " ORDER BY " . implode(", ", $orders);

    }
    elseif (!empty($request['groupBy'])) {

        $sql .= " ORDER BY " . $request['groupBy'][0];

    }
    else {

        $sql .= " ORDER BY 1";

    }

        /*
         * Pagination
         */
        if (
            isset($request['page']) &&
            isset($request['pageSize'])
        ) {

            $offset =
                ($request['page'] - 1)
                * $request['pageSize'];

            $sql .= "
                OFFSET {$offset} ROWS
                FETCH NEXT {$request['pageSize']} ROWS ONLY
            ";
        }

        return $this->queryEngine->executePrepared(
            $sql,
            $params
        );
    }
}