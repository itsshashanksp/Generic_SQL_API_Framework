<?php

require_once __DIR__ . '/../../core/QueryEngine.php';
require_once __DIR__ . '/MetadataRepository.php';
require_once __DIR__ . '/SetOperationBuilder.php';

class QueryRepository
{
    private QueryEngine $queryEngine;
    private MetadataRepository $metadataRepository;
    private SetOperationBuilder $SetOperationBuilder;

    private ?int $sqlServerCompatibilityLevel = null;
    
    // Table & Alias Map
    private array $tableMap = [];

    public function __construct()
    {
        $this->queryEngine = new QueryEngine();
        $this->metadataRepository = new MetadataRepository();
        $this->SetOperationBuilder = new SetOperationBuilder($this);
    }

    /**
     * Detect SQL Server database compatibility level.
     *
     * SQL Server compatibility level 110+
     * supports OFFSET/FETCH.
     *
     * Older compatibility levels use ROW_NUMBER().
     */
    private function getSqlServerCompatibilityLevel(): int
    {
        if ($this->sqlServerCompatibilityLevel !== null) {
            return $this->sqlServerCompatibilityLevel;
        }

        $result = $this->queryEngine->executePrepared(
            "
            SELECT compatibility_level AS CompatibilityLevel
            FROM sys.databases
            WHERE name = DB_NAME()
            ",
            []
        );

        if (
            empty($result['data']) ||
            !isset($result['data'][0]['CompatibilityLevel'])
        ) {
            throw new Exception(
                "Unable to determine SQL Server compatibility level."
            );
        }

        $this->sqlServerCompatibilityLevel =
            (int)$result['data'][0]['CompatibilityLevel'];

        return $this->sqlServerCompatibilityLevel;
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

/*
 * Build SQL Value
 */
private function buildValue($value): string
{

    /*
     * Numeric
     */
    if (is_numeric($value)) {

        return (string)$value;

    }

    /*
     * NULL
     */
    if ($value === null) {

        return "NULL";

    }

    /*
     * Boolean
     */
    if (is_bool($value)) {

        return $value ? "1" : "0";

    }

    /*
     * Nested Expression
     */
    if (is_array($value)) {

        return $this->buildExpression($value);

    }

    /*
     * String
     */
    return "'"
        . str_replace("'", "''", $value)
        . "'";

}

/*
 * Build SQL Expression
 */
private function buildExpression($expression): string
{

    /*
     * Primitive Value
     */
    if (
        is_numeric($expression)
        || is_string($expression)
        || is_bool($expression)
        || $expression === null
    ) {

        return $this->buildValue($expression);

    }

    /*
     * Column
     */
    if (isset($expression["column"])) {

        $resolved =
            $this->resolveColumn(
                $expression["column"]
            );

        if (!empty($resolved["table"])) {

            return
                $resolved["table"]
                . "."
                . $resolved["column"];

        }

        return $resolved["column"];

    }

    /*
     * Arithmetic Expression
     */
    if (isset($expression["expression"])) {

        return "("
            . $this->buildExpression(
                $expression["expression"]["left"]
            )
            . " "
            . $expression["expression"]["operator"]
            . " "
            . $this->buildExpression(
                $expression["expression"]["right"]
            )
            . ")";

    }

    throw new Exception(
        "Unsupported expression."
    );

}

/*
 * Build SQL Condition
 */
private function buildCondition(array $condition): string
{

    /*
     * Required Fields
     */
    foreach ([
        "left",
        "operator",
        "right"
    ] as $field) {

        if (!array_key_exists($field, $condition)) {

            throw new Exception(
                "Condition requires {$field}."
            );

        }

    }

    /*
     * Allowed Operators
     */
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
        "NOT IN"

    ];

    $operator =
        strtoupper($condition["operator"]);

    if (
        !in_array(
            $operator,
            $allowedOperators
        )
    ) {

        throw new Exception(
            "Invalid condition operator."
        );

    }

    /*
     * IN / NOT IN
     */
    if (
        $operator == "IN"
        || $operator == "NOT IN"
    ) {

        if (
            !is_array($condition["right"])
            || empty($condition["right"])
        ) {

            throw new Exception(
                "{$operator} requires an array."
            );

        }

        $values = [];

        foreach ($condition["right"] as $value) {

            $values[] =
                $this->buildValue($value);

        }

        return
            $this->buildExpression(
                $condition["left"]
            )
            . " "
            . $operator
            . " ("
            . implode(", ", $values)
            . ")";

    }

    /*
     * Normal Comparison
     */
    return
        $this->buildExpression(
            $condition["left"]
        )
        . " "
        . $operator
        . " "
        . $this->buildExpression(
            $condition["right"]
        );

}

/*
 * Execute Prepared SQL
 */
public function select($request)
{
    if (isset($request['queries'])) {
        return $this->SetOperationBuilder->build($request);
    }

    $query = $this->buildSelect($request);

    $result = $this->queryEngine->executePrepared(
        $query['sql'],
        $query['params']
    );

    if ($query['totalRows'] !== null) {
        $result['totalRows'] = $query['totalRows'];
    }

    return $result;
}

/*
 * Execute Procedure
 */
public function procedure(array $request)
{
    $query = $this->buildProcedure($request);

    return $this->queryEngine->executePreparedQuery(
        $query["sql"],
        $query["params"]
    );
}

/*
 * Execute Function
 */ 
public function function(array $request)
{
    $query = $this->buildFunction($request);

    return $this->queryEngine->executePreparedQuery(
        $query["sql"],
        $query["params"]
    );
}

/*
 * Execute Table Function
 */
public function tableFunction(array $request)
{
    $query = $this->buildTableFunction($request);

    return $this->queryEngine->executePreparedQuery(
        $query["sql"],
        $query["params"]
    );
}

    /*
    |--------------------------------------------------------------------------
    | Select Query Builder
    |--------------------------------------------------------------------------
    */
    public function buildSelect($request, bool $isUnion = false)
    {

    $params = [];
    $totalRows = null;

/*
*Recursive CTE
*/
$cteSql = "";

if (isset($request["recursiveCte"])) {

    $cte = $request["recursiveCte"];

    foreach ([
        "name",
        "anchor",
        "recursive"
    ] as $field) {

        if (empty($cte[$field])) {

            throw new Exception(
                "Recursive CTE requires {$field}."
            );

        }

    }

    $anchor =
        $this->buildSelect(
            $cte["anchor"],
            true
        );

    $recursive =
        $this->buildSelect(
            $cte["recursive"],
            true
        );

    $cteSql =
        "WITH "
        . $cte["name"]
        . " AS ("
        . $anchor["sql"]
        . " UNION ALL "
        . $recursive["sql"]
        . ") ";

    $params = array_merge(
        $params,
        $anchor["params"],
        $recursive["params"]
    );
}

/*
 * CTE Request
 */

if (isset($request['cte'])) {

    $cte = $request['cte'];

    if (
        empty($cte['name'])
        || empty($cte['query'])
    ) {
        throw new Exception(
            "CTE requires name and query."
        );
    }

    $subQuery = $this->buildSelect(
        $cte['query'],
        true
    );

    $cteSql =
        "WITH {$cte['name']} AS ({$subQuery['sql']}) ";

    $params = array_merge(
        $params,
        $subQuery['params']
    );
}

        /*
         * Validate Table
         */
         $isCTE =
         (
             isset($request['cte']) &&
             $request['table'] === $request['cte']['name']
         )
         ||
         (
             isset($request['recursiveCte']) &&
             $request['table'] === $request['recursiveCte']['name']
         );

         if (
             !$isCTE &&
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
 * CASE Expression Validation
 */
if (isset($column['case'])) {

    $case = $column['case'];

    if (
        empty($case['when'])
        || !is_array($case['when'])
    ) {
        throw new Exception(
            "CASE requires at least one WHEN clause."
        );
    }

    foreach ($case['when'] as $when) {

        if (empty($when['condition'])) {
            throw new Exception(
                "CASE WHEN requires a condition."
            );
        }

        $condition = $when['condition'];

        if (empty($condition['column'])) {
            throw new Exception(
                "CASE condition requires column."
            );
        }

        if (empty($condition['operator'])) {
            throw new Exception(
                "CASE condition requires operator."
            );
        }

        if (!array_key_exists("value", $condition)) {
            throw new Exception(
                "CASE condition requires value."
            );
        }

        if (!array_key_exists("then", $when)) {
            throw new Exception(
                "CASE WHEN requires THEN value."
            );
        }

        $resolved =
            $this->resolveColumn(
                $condition['column']
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
                "Invalid CASE column: {$condition['column']}"
            );
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
                strtoupper($condition['operator']),
                $allowedOperators
            )
        ) {
            throw new Exception(
                "Invalid CASE operator: {$condition['operator']}"
            );
        }
    }

    continue;
}

/*
 * Arithmetic Expression
 */
if (isset($column['expression'])) {

    $expression = $column['expression'];

    foreach (['left','right'] as $side) {

        if (is_numeric($expression[$side])) {

            $$side = $expression[$side];

        } else {

            $resolved =
                $this->resolveColumn(
                    $expression[$side]
                );

            if (!empty($resolved['table'])) {

                $$side =
                    $resolved['table']
                    . "."
                    . $resolved['column'];

            } else {

                $$side =
                    $resolved['column'];

            }

        }

    }

    $alias =
        $column['alias']
        ?? "Expression";

    $selectColumns[] =
        "("
        . $left
        . " "
        . $expression['operator']
        . " "
        . $right
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * Aggregate Column
 */
if (isset($column['function'])) {

$functionsWithoutColumn = [
    "GETDATE",
    "COALESCE",
    "CONCAT",
    "DATEDIFF",
    "EOMONTH",
    "DATEFROMPARTS",
    "DATETIMEFROMPARTS",
    "TIMEFROMPARTS",
    "SYSDATETIME",
    "CURRENT_TIMESTAMP",
    "IIF",
    "CHOOSE",
    "ROW_NUMBER",
    "RANK",
    "DENSE_RANK",
    "NTILE",
];

if (
    !in_array(
        strtoupper($column['function']),
        $functionsWithoutColumn
    )
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

/*
 * COALESCE Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "COALESCE"
) {

    if (
        empty($column['columns'])
        || !is_array($column['columns'])
    ) {
        throw new Exception(
            "COALESCE requires columns."
        );
    }

    foreach ($column['columns'] as $coalesceColumn) {

        $resolved =
            $this->resolveColumn(
                $coalesceColumn
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
                "Invalid column: {$coalesceColumn}"
            );
        }
    }

    continue;
    }

/*
 * DATEADD Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "DATEADD"
) {

    if (!isset($column['datepart'])) {

        throw new Exception(
            "DATEADD requires datepart."
        );

    }

    if (!isset($column['number'])) {

        throw new Exception(
            "DATEADD requires number."
        );

    }

    continue;

}

/*
 * DATEDIFF Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "DATEDIFF"
) {

    if (!isset($column['datepart'])) {

        throw new Exception(
            "DATEDIFF requires datepart."
        );

    }

    if (!isset($column['start'])) {

        throw new Exception(
            "DATEDIFF requires start."
        );

    }

    if (!isset($column['end'])) {

        throw new Exception(
            "DATEDIFF requires end."
        );

    }

    continue;

}

}

/*
 * EOMONTH Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "EOMONTH"
) {

    if (!isset($column['start'])) {

        throw new Exception(
            "EOMONTH requires start."
        );

    }

    continue;

}

/*
 * ISDATE Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "ISDATE"
) {

    if (empty($column['column'])) {

        throw new Exception(
            "ISDATE requires column."
        );

    }

    continue;

}

/*
 * DATEFROMPARTS Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "DATEFROMPARTS"
) {

    if (!isset($column['year'])) {

        throw new Exception(
            "DATEFROMPARTS requires year."
        );

    }

    if (!isset($column['month'])) {

        throw new Exception(
            "DATEFROMPARTS requires month."
        );

    }

    if (!isset($column['day'])) {

        throw new Exception(
            "DATEFROMPARTS requires day."
        );

    }

    continue;

}

/*
 * DATETIMEFROMPARTS Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "DATETIMEFROMPARTS"
) {

    $required = [
        "year",
        "month",
        "day",
        "hour",
        "minute",
        "second",
        "millisecond"
    ];

    foreach ($required as $field) {

        if (!isset($column[$field])) {

            throw new Exception(
                "DATETIMEFROMPARTS requires {$field}."
            );

        }

    }

    continue;

}

/*
 * TIMEFROMPARTS Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "TIMEFROMPARTS"
) {

    $required = [
        "hour",
        "minute",
        "second",
        "fractions",
        "precision"
    ];

    foreach ($required as $field) {

        if (!isset($column[$field])) {

            throw new Exception(
                "TIMEFROMPARTS requires {$field}."
            );

        }

    }

    continue;

}

/*
 * SYSDATETIME Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "SYSDATETIME"
) {

    continue;

}

/*
 * CURRENT_TIMESTAMP Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "CURRENT_TIMESTAMP"
) {

    continue;

}

/*
 * IIF Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "IIF"
) {

    foreach (["condition", "true", "false"] as $field) {

        if (!isset($column[$field])) {

            throw new Exception(
                "IIF requires {$field}."
            );

        }

    }

    continue;
}

/*
 * CHOOSE Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "CHOOSE"
) {

    if (!isset($column['index'])) {

        throw new Exception(
            "CHOOSE requires index."
        );

    }

    if (
        empty($column['values'])
        || !is_array($column['values'])
    ) {

        throw new Exception(
            "CHOOSE requires values."
        );

    }

    if (count($column['values']) < 2) {

        throw new Exception(
            "CHOOSE requires at least two values."
        );

    }

    continue;

}

/*
 * ROW_NUMBER Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "ROW_NUMBER"
) {

    if (
        empty($column['orderBy'])
        || !is_array($column['orderBy'])
    ) {

        throw new Exception(
            "ROW_NUMBER requires orderBy."
        );

    }

    continue;

}

/*
 * RANK Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "RANK"
) {

    if (
        empty($column['orderBy']) ||
        !is_array($column['orderBy'])
    ) {

        throw new Exception(
            "RANK requires orderBy."
        );

    }

    continue;

}

/*
 * DENSE_RANK Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "DENSE_RANK"
) {

    if (
        empty($column['orderBy']) ||
        !is_array($column['orderBy'])
    ) {

        throw new Exception(
            "DENSE_RANK requires orderBy."
        );

    }

    continue;

}

/*
 * NTILE Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "NTILE"
) {

    if (!isset($column['buckets'])) {

        throw new Exception(
            "NTILE requires buckets."
        );

    }

    if (
        !is_numeric($column['buckets'])
        || $column['buckets'] <= 0
    ) {

        throw new Exception(
            "NTILE buckets must be greater than zero."
        );

    }

    if (
        empty($column['orderBy'])
        || !is_array($column['orderBy'])
    ) {

        throw new Exception(
            "NTILE requires orderBy."
        );

    }

    continue;

}

/*
 * LAG Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "LAG"
) {

    if (empty($column['column'])) {

        throw new Exception(
            "LAG requires column."
        );

    }

    if (
        empty($column['orderBy']) ||
        !is_array($column['orderBy'])
    ) {

        throw new Exception(
            "LAG requires orderBy."
        );

    }

    if (
        isset($column['offset']) &&
        (
            !is_numeric($column['offset']) ||
            $column['offset'] < 1
        )
    ) {

        throw new Exception(
            "LAG offset must be greater than zero."
        );

    }

    continue;

}

/*
 * LEAD Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "LEAD"
) {

    if (empty($column['column'])) {

        throw new Exception(
            "LEAD requires column."
        );

    }

    if (
        empty($column['orderBy']) ||
        !is_array($column['orderBy'])
    ) {

        throw new Exception(
            "LEAD requires orderBy."
        );

    }

    if (
        isset($column['offset']) &&
        (
            !is_numeric($column['offset']) ||
            $column['offset'] < 1
        )
    ) {

        throw new Exception(
            "LEAD offset must be greater than zero."
        );

    }

    continue;

}

/*
 * FIRST_VALUE Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "FIRST_VALUE"
) {

    if (empty($column['column'])) {

        throw new Exception(
            "FIRST_VALUE requires column."
        );

    }

    if (
        empty($column['orderBy']) ||
        !is_array($column['orderBy'])
    ) {

        throw new Exception(
            "FIRST_VALUE requires orderBy."
        );

    }

    continue;

}

/*
 * LAST_VALUE Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "LAST_VALUE"
) {

    if (empty($column['column'])) {

        throw new Exception(
            "LAST_VALUE requires column."
        );

    }

    if (
        empty($column['orderBy']) ||
        !is_array($column['orderBy'])
    ) {

        throw new Exception(
            "LAST_VALUE requires orderBy."
        );

    }

    continue;

}

/*
 * STRING_AGG Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "STRING_AGG"
) {

    if (empty($column['column'])) {

        throw new Exception(
            "STRING_AGG requires column."
        );

    }

    if (!isset($column['separator'])) {

        throw new Exception(
            "STRING_AGG requires separator."
        );

    }

    if (
        isset($column['orderBy']) &&
        !is_array($column['orderBy'])
    ) {

        throw new Exception(
            "STRING_AGG orderBy must be an array."
        );

    }

    continue;

}

/*
 * CAST / CONVERT Validation
 */
if (
    isset($column['function']) &&
    in_array(
        strtoupper($column['function']),
        [
            "CAST",
            "CONVERT"
        ]
    )
) {

    if (empty($column['datatype'])) {
        throw new Exception(
            strtoupper($column['function'])
            . " requires datatype."
        );
    }

    continue;
}

/*
 * NULLIF Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "NULLIF"
) {

    if (!array_key_exists("value", $column)) {
        throw new Exception(
            "NULLIF requires value."
        );
    }

    continue;
}

/*
 * CONCAT Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "CONCAT"
) {

    if (
        empty($column['columns']) ||
        !is_array($column['columns']) ||
        count($column['columns']) < 2
    ) {

        throw new Exception(
            "CONCAT requires at least two values."
        );

    }

    foreach ($column['columns'] as $value) {

        if (
            is_string($value) &&
            preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $value)
        ) {

            $resolved =
                $this->resolveColumn($value);

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
                    "Invalid column: {$value}"
                );

            }

        }

    }

    continue;

}

/*
 * LEFT / RIGHT Validation
 */
if (
    isset($column['function']) &&
    in_array(
        strtoupper($column['function']),
        [
            "LEFT",
            "RIGHT"
        ]
    )
) {

    if (!isset($column['length'])) {

        throw new Exception(
            strtoupper($column['function'])
            . " requires length."
        );

    }

    continue;

}

/*
 * SUBSTRING Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function'])
    == "SUBSTRING"
) {

    if (!isset($column['start'])) {

        throw new Exception(
            "SUBSTRING requires start."
        );

    }

    if (!isset($column['length'])) {

        throw new Exception(
            "SUBSTRING requires length."
        );

    }

    continue;

}

/*
 * REPLACE Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "REPLACE"
) {

    if (!array_key_exists("search", $column)) {

        throw new Exception(
            "REPLACE requires search."
        );

    }

    if (!array_key_exists("replace", $column)) {

        throw new Exception(
            "REPLACE requires replace."
        );

    }

    continue;

}

/*
 * CHARINDEX Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "CHARINDEX"
) {

    if (!array_key_exists("search", $column)) {

        throw new Exception(
            "CHARINDEX requires search."
        );

    }

    continue;

}

/*
 * PATINDEX Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "PATINDEX"
) {

    if (!array_key_exists("pattern", $column)) {

        throw new Exception(
            "PATINDEX requires pattern."
        );

    }

    continue;

}

/*
 * FORMAT Validation
 */
if (
    isset($column['function']) &&
    strtoupper($column['function']) == "FORMAT"
) {

    if (!array_key_exists("format", $column)) {

        throw new Exception(
            "FORMAT requires format."
        );

    }

    continue;

}

        /*
         * Normal Column With Alias
         */
        else {

            $resolved = $this->resolveColumn($column['column']);

            $table = $resolved['table'] ?? $request['table'];

            $isCTEColumn =
            (
                isset($request['cte']) &&
                $table === $request['cte']['name']
            )
            ||
            (
              isset($request['recursiveCte']) &&
                $table === $request['recursiveCte']['name']
            );

            if ($isCTEColumn) {
                continue;
}

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

        // Allow SELECT *
        if ($column === "*") {
            continue;
        }

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
 * CASE Expression
 */
if (isset($column['case'])) {

    $case = $column['case'];

    $alias =
        $case['alias']
        ?? "CaseValue";

    $caseSql = "CASE ";

    foreach ($case['when'] as $when) {

        $condition =
            $when['condition'];

        $resolved =
            $this->resolveColumn(
                $condition['column']
            );

        if (!empty($resolved['table'])) {

            $columnName =
                $resolved['table']
                . "."
                . $resolved['column'];

        } else {

            $columnName =
                $resolved['column'];

        }

        $value = $condition['value'];

        if (is_string($value)) {
            $value = "'" . $value . "'";
        }

        $then = $when['then'];

        if (is_string($then)) {
            $then = "'" . $then . "'";
        }

        $caseSql .=
            "WHEN "
            . $columnName
            . " "
            . $condition['operator']
            . " "
            . $value
            . " THEN "
            . $then
            . " ";
    }

    if (array_key_exists("else", $case)) {

        $else = $case['else'];

        if (is_string($else)) {
            $else = "'" . $else . "'";
        }

        $caseSql .=
            "ELSE "
            . $else
            . " ";
    }

    $caseSql .=
        "END AS [{$alias}]";

    $selectColumns[] =
        $caseSql;

    continue;
}        

/*
 * Arithmetic Expression Validation
 */
if (isset($column['expression'])) {

    $expression = $column['expression'];

    foreach (['left','operator','right'] as $field) {

        if (!isset($expression[$field])) {
            throw new Exception(
                "Expression requires {$field}."
            );
        }

    }

    $allowedOperators = [
        "+",
        "-",
        "*",
        "/",
        "%"
    ];

    if (
        !in_array(
            $expression['operator'],
            $allowedOperators
        )
    ) {

        throw new Exception(
            "Invalid expression operator."
        );

    }

    foreach (['left','right'] as $side) {

        if (!is_numeric($expression[$side])) {

            $resolved =
                $this->resolveColumn(
                    $expression[$side]
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
                    "Invalid column: {$expression[$side]}"
                );

            }

        }

    }

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
        "MAX",
        "STRING_AGG",
    ];

    $stringFunctions = [
        "UPPER",
        "LOWER",
        "LTRIM",
        "RTRIM",
        "TRIM",
        "LEN",

        "COALESCE",
        "ISNULL",

        "CAST",
        "CONVERT",

        "NULLIF",

        "CONCAT",

        "LEFT",
        "RIGHT",
        "SUBSTRING",

        "REPLACE",

        "CHARINDEX",

        "PATINDEX",

        "FORMAT",

        "CHOOSE",
    ];

    $dateFunctions = [
        "YEAR",
        "MONTH",
        "DAY",
        "DATEPART",
        "DATENAME",
        "GETDATE",
        "DATEADD",
        "DATEDIFF",
        "EOMONTH",
        "ISDATE",
        "DATEFROMPARTS",
        "DATETIMEFROMPARTS",
        "TIMEFROMPARTS",
        "SYSDATETIME",
        "CURRENT_TIMESTAMP",
        "IIF",
    ];

    $mathFunctions = [
        "ABS",
        "ROUND",
        "CEILING",
        "FLOOR",
        "POWER",
        "SQRT",
        "EXP",
        "LOG",
    ];

    $windowFunctions = [
        "ROW_NUMBER",
        "RANK",
        "DENSE_RANK",
        "NTILE",
        "LAG",
        "LEAD",
        "FIRST_VALUE",
        "LAST_VALUE"
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
        &&
        !in_array($function, $windowFunctions)
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
 * COALESCE()
 */
if ($function == "COALESCE") {

    if (
        empty($column['columns'])
        || !is_array($column['columns'])
    ) {
        throw new Exception(
            "COALESCE requires columns."
        );
    }

    $coalesceColumns = [];

    foreach ($column['columns'] as $coalesceColumn) {

        $resolved =
            $this->resolveColumn(
                $coalesceColumn
            );

        if (!empty($resolved['table'])) {

            $coalesceColumns[] =
                $resolved['table']
                . "."
                . $resolved['column'];

        } else {

            $coalesceColumns[] =
                $resolved['column'];

        }
    }

    if (isset($column['default'])) {

        $default = $column['default'];

        if (is_string($default)) {
            $default = "'" . $default . "'";
        }

        $coalesceColumns[] = $default;
    }

    $selectColumns[] =
        "COALESCE("
        . implode(", ", $coalesceColumns)
        . ") AS [{$alias}]";

    continue;
}

/*
 * ISNULL()
 */
if ($function == "ISNULL") {

    if (empty($column['column'])) {
        throw new Exception(
            "ISNULL requires column."
        );
    }

    if (!array_key_exists("default", $column)) {
        throw new Exception(
            "ISNULL requires default."
        );
    }

    $default = $column['default'];

    if (is_string($default)) {
        $default = "'" . $default . "'";
    }

    $selectColumns[] =
        "ISNULL("
        . $resolvedColumn
        . ", "
        . $default
        . ") AS [{$alias}]";

    continue;
}
/*
 * CAST()
 */
if ($function == "CAST") {

    if (empty($column['datatype'])) {
        throw new Exception(
            "CAST requires datatype."
        );
    }

    $selectColumns[] =
        "CAST("
        . $resolvedColumn
        . " AS "
        . strtoupper($column['datatype'])
        . ") AS ["
        . $alias
        . "]";

    continue;
}

/*
 * CONVERT()
 */
if ($function == "CONVERT") {

    if (empty($column['datatype'])) {
        throw new Exception(
            "CONVERT requires datatype."
        );
    }

    $sql =
        "CONVERT("
        . strtoupper($column['datatype'])
        . ", "
        . $resolvedColumn;

    if (isset($column['style'])) {

        $sql .=
            ", "
            . (int)$column['style'];

    }

    $sql .=
        ") AS ["
        . $alias
        . "]";

    $selectColumns[] = $sql;

    continue;
}

/*
 * NULLIF()
 */
if ($function == "NULLIF") {

    if (!array_key_exists("value", $column)) {
        throw new Exception(
            "NULLIF requires value."
        );
    }

    $value = $column['value'];

    if (is_string($value)) {
        $value = "'" . $value . "'";
    }

    $selectColumns[] =
        "NULLIF("
        . $resolvedColumn
        . ", "
        . $value
        . ") AS ["
        . $alias
        . "]";

    continue;
}

/*
 * CONCAT()
 */
if ($function == "CONCAT") {

    $parts = [];

    foreach ($column['columns'] as $value) {

        if (is_numeric($value)) {

            $parts[] = $value;

        } elseif (
            is_string($value) &&
            preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $value)
        ) {

            $resolved =
                $this->resolveColumn($value);

            if (!empty($resolved['table'])) {

                $parts[] =
                    $resolved['table']
                    . "."
                    . $resolved['column'];

            } else {

                $parts[] =
                    $resolved['column'];

            }

        } else {

            $parts[] =
                "'" .
                str_replace("'", "''", $value)
                . "'";

        }

    }

    $selectColumns[] =
        "CONCAT("
        . implode(", ", $parts)
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * LEFT()
 */
if ($function == "LEFT") {

    $selectColumns[] =
        "LEFT("
        . $resolvedColumn
        . ", "
        . (int)$column['length']
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * RIGHT()
 */
if ($function == "RIGHT") {

    $selectColumns[] =
        "RIGHT("
        . $resolvedColumn
        . ", "
        . (int)$column['length']
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * SUBSTRING()
 */
if ($function == "SUBSTRING") {

    $selectColumns[] =
        "SUBSTRING("
        . $resolvedColumn
        . ", "
        . (int)$column['start']
        . ", "
        . (int)$column['length']
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * REPLACE()
 */
if ($function == "REPLACE") {

    $search =
        str_replace(
            "'",
            "''",
            $column['search']
        );

    $replace =
        str_replace(
            "'",
            "''",
            $column['replace']
        );

    $selectColumns[] =
        "REPLACE("
        . $resolvedColumn
        . ", '"
        . $search
        . "', '"
        . $replace
        . "') AS ["
        . $alias
        . "]";

    continue;

}

/*
 * CHARINDEX()
 */
if ($function == "CHARINDEX") {

    $search =
        str_replace(
            "'",
            "''",
            $column['search']
        );

    $selectColumns[] =
        "CHARINDEX('"
        . $search
        . "', "
        . $resolvedColumn
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * PATINDEX()
 */
if ($function == "PATINDEX") {

    $pattern =
        str_replace(
            "'",
            "''",
            $column['pattern']
        );

    $selectColumns[] =
        "PATINDEX('"
        . $pattern
        . "', CAST("
        . $resolvedColumn
        . " AS NVARCHAR(MAX))) AS ["
        . $alias
        . "]";

    continue;

}

/*
 * FORMAT()
 */
if ($function == "FORMAT") {

    $format = str_replace(
        "'",
        "''",
        $column['format']
    );

    if (isset($column['style'])) {

        $style = (int)$column['style'];

        $selectColumns[] =
            "FORMAT("
            . "CONVERT(date, CAST("
            . $resolvedColumn
            . " AS varchar(8)), "
            . $style
            . "), '"
            . $format
            . "') AS ["
            . $alias
            . "]";

    } else {

        $selectColumns[] =
            "FORMAT("
            . $resolvedColumn
            . ", '"
            . $format
            . "') AS ["
            . $alias
            . "]";

    }

    continue;

}

/*
 * DATEADD()
 */
if ($function == "DATEADD") {

    $datePart = strtoupper($column['datepart']);

    $allowedParts = [
        "YEAR",
        "MONTH",
        "DAY",
        "HOUR",
        "MINUTE",
        "SECOND"
    ];

    if (!in_array($datePart, $allowedParts)) {

        throw new Exception(
            "Invalid DATEADD datepart."
        );

    }

    /*
     * Legacy YYYYMMDD Support
     */
    if (isset($column['style'])) {

        $style = (int)$column['style'];

        $dateExpression =
            "CONVERT(date, CAST("
            . $resolvedColumn
            . " AS varchar(8)), "
            . $style
            . ")";

    } else {

        $dateExpression = $resolvedColumn;

    }

    $selectColumns[] =
        "DATEADD("
        . $datePart
        . ", "
        . (int)$column['number']
        . ", "
        . $dateExpression
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * DATEDIFF()
 */
if ($function == "DATEDIFF") {

    $datePart = strtoupper($column['datepart']);

    $allowedParts = [
        "YEAR",
        "QUARTER",
        "MONTH",
        "DAYOFYEAR",
        "DAY",
        "WEEK",
        "WEEKDAY",
        "HOUR",
        "MINUTE",
        "SECOND",
        "MILLISECOND"
    ];

    if (!in_array($datePart, $allowedParts)) {

        throw new Exception(
            "Invalid DATEDIFF datepart."
        );

    }

    /*
     * START
     */
    if (isset($column['start']['function'])) {

        $start =
            strtoupper($column['start']['function'])
            . "()";

    } else {

        $start = $column['start']['column'];

        if (isset($column['start']['style'])) {

            $start =
                "CONVERT(date, CAST("
                . $start
                . " AS varchar(8)), "
                . (int)$column['start']['style']
                . ")";
        }
    }

    /*
     * END
     */
    if (isset($column['end']['function'])) {

        $end =
            strtoupper($column['end']['function'])
            . "()";

    } else {

        $end = $column['end']['column'];

        if (isset($column['end']['style'])) {

            $end =
                "CONVERT(date, CAST("
                . $end
                . " AS varchar(8)), "
                . (int)$column['end']['style']
                . ")";
        }
    }

    $selectColumns[] =
        "DATEDIFF("
        . $datePart
        . ", "
        . $start
        . ", "
        . $end
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * EOMONTH()
 */
if ($function == "EOMONTH") {

    /*
     * START
     */
    if (isset($column['start']['function'])) {

        $start =
            strtoupper(
                $column['start']['function']
            ) . "()";

    } else {

        $resolved =
            $this->resolveColumn(
                $column['start']['column']
            );

        if (!empty($resolved['table'])) {

            $start =
                $resolved['table']
                . "."
                . $resolved['column'];

        } else {

            $start =
                $resolved['column'];

        }

        if (isset($column['start']['style'])) {

            $start =
                "CONVERT(date, CAST("
                . $start
                . " AS varchar(8)), "
                . (int)$column['start']['style']
                . ")";

        }

    }

    $monthOffset =
        $column['month']
        ?? 0;

    $selectColumns[] =
        "EOMONTH("
        . $start
        . ", "
        . (int)$monthOffset
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * ISDATE()
 */
if ($function == "ISDATE") {

    $value = $resolvedColumn;

    if (isset($column['style'])) {

        $value =
            "CONVERT(varchar(50), "
            . $resolvedColumn
            . ", "
            . (int)$column['style']
            . ")";

    }

    $selectColumns[] =
        "ISDATE("
        . $value
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * DATEFROMPARTS()
 */
if ($function == "DATEFROMPARTS") {

    $year = is_array($column['year'])
        ? $this->buildExpression($column['year'])
        : (int)$column['year'];

    $month = is_array($column['month'])
        ? $this->buildExpression($column['month'])
        : (int)$column['month'];

    $day = is_array($column['day'])
        ? $this->buildExpression($column['day'])
        : (int)$column['day'];

    $selectColumns[] =
        "DATEFROMPARTS("
        . $year
        . ", "
        . $month
        . ", "
        . $day
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * DATETIMEFROMPARTS()
 */
if ($function == "DATETIMEFROMPARTS") {

    $parts = [];

    foreach ([
        "year",
        "month",
        "day",
        "hour",
        "minute",
        "second",
        "millisecond"
    ] as $part) {

        if (is_array($column[$part])) {

            $parts[] =
                $this->buildExpression(
                    $column[$part]
                );

        } else {

            $parts[] =
                (int)$column[$part];

        }

    }

    $selectColumns[] =
        "DATETIMEFROMPARTS("
        . implode(", ", $parts)
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * TIMEFROMPARTS()
 */
if ($function == "TIMEFROMPARTS") {

    $parts = [];

    foreach ([
        "hour",
        "minute",
        "second",
        "fractions",
        "precision"
    ] as $part) {

        if (is_array($column[$part])) {

            $parts[] =
                $this->buildExpression(
                    $column[$part]
                );

        } else {

            $parts[] =
                (int)$column[$part];

        }

    }

    $selectColumns[] =
        "TIMEFROMPARTS("
        . implode(", ", $parts)
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * SYSDATETIME()
 */
if ($function == "SYSDATETIME") {

    $selectColumns[] =
        "SYSDATETIME() AS ["
        . $alias
        . "]";

    continue;

}

/*
 * CURRENT_TIMESTAMP
 */
if ($function == "CURRENT_TIMESTAMP") {

    $selectColumns[] =
        "CURRENT_TIMESTAMP AS ["
        . $alias
        . "]";

    continue;

}

/*
 * IIF()
 */
if ($function == "IIF") {

    $selectColumns[] =
        "IIF("
        . $this->buildCondition($column["condition"])
        . ", "
        . $this->buildExpression($column["true"])
        . ", "
        . $this->buildExpression($column["false"])
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * CHOOSE()
 */
if ($function == "CHOOSE") {

    $values = [];

    foreach ($column["values"] as $value) {

        $values[] =
            $this->buildExpression($value);

    }

    $selectColumns[] =
        "CHOOSE("
        . (int)$column["index"]
        . ", "
        . implode(", ", $values)
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * ROW_NUMBER()
 */
if ($function == "ROW_NUMBER") {

    $orders = [];

    foreach ($column["orderBy"] as $order) {

        $resolved =
            $this->resolveColumn(
                $order["column"]
            );

        $columnName =
            !empty($resolved["table"])
            ? $resolved["table"] . "." . $resolved["column"]
            : $resolved["column"];

        $direction =
            strtoupper(
                $order["direction"] ?? "ASC"
            );

        $orders[] =
            $columnName
            . " "
            . $direction;

    }

    $selectColumns[] =
        "ROW_NUMBER() OVER (ORDER BY "
        . implode(", ", $orders)
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * RANK()
 */
if ($function == "RANK") {

    $orders = [];

    foreach ($column["orderBy"] as $order) {

        $resolved =
            $this->resolveColumn(
                $order["column"]
            );

        $columnName =
            !empty($resolved["table"])
            ? $resolved["table"] . "." . $resolved["column"]
            : $resolved["column"];

        $direction =
            strtoupper(
                $order["direction"] ?? "ASC"
            );

        $orders[] =
            $columnName
            . " "
            . $direction;

    }

    $selectColumns[] =
        "RANK() OVER (ORDER BY "
        . implode(", ", $orders)
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * DENSE_RANK()
 */
if ($function == "DENSE_RANK") {

    $orders = [];

    foreach ($column["orderBy"] as $order) {

        $resolved =
            $this->resolveColumn(
                $order["column"]
            );

        $columnName =
            !empty($resolved["table"])
            ? $resolved["table"] . "." . $resolved["column"]
            : $resolved["column"];

        $direction =
            strtoupper(
                $order["direction"] ?? "ASC"
            );

        $orders[] =
            $columnName
            . " "
            . $direction;

    }

    $selectColumns[] =
        "DENSE_RANK() OVER (ORDER BY "
        . implode(", ", $orders)
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * NTILE()
 */
if ($function == "NTILE") {

    $orders = [];

    foreach ($column["orderBy"] as $order) {

        $resolved =
            $this->resolveColumn(
                $order["column"]
            );

        $columnName =
            !empty($resolved["table"])
            ? $resolved["table"] . "." . $resolved["column"]
            : $resolved["column"];

        $direction =
            strtoupper(
                $order["direction"] ?? "ASC"
            );

        $orders[] =
            $columnName
            . " "
            . $direction;

    }

    $selectColumns[] =
        "NTILE("
        . (int)$column["buckets"]
        . ") OVER (ORDER BY "
        . implode(", ", $orders)
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * LAG()
 */
if ($function == "LAG") {

    $orders = [];

    foreach ($column["orderBy"] as $order) {

        $resolved =
            $this->resolveColumn(
                $order["column"]
            );

        $columnName =
            !empty($resolved["table"])
            ? $resolved["table"] . "." . $resolved["column"]
            : $resolved["column"];

        $direction =
            strtoupper(
                $order["direction"] ?? "ASC"
            );

        $orders[] =
            $columnName
            . " "
            . $direction;

    }

    $offset =
        (int)($column["offset"] ?? 1);

    $sql =
        "LAG("
        . $resolvedColumn
        . ", "
        . $offset;

    if (array_key_exists("default", $column)) {

        $default =
            $this->buildValue(
                $column["default"]
            );

        $sql .=
            ", "
            . $default;

    }

    $sql .=
        ") OVER (ORDER BY "
        . implode(", ", $orders)
        . ") AS ["
        . $alias
        . "]";

    $selectColumns[] = $sql;

    continue;

}

/*
 * LEAD()
 */
if ($function == "LEAD") {

    $orders = [];

    foreach ($column["orderBy"] as $order) {

        $resolved =
            $this->resolveColumn(
                $order["column"]
            );

        $columnName =
            !empty($resolved["table"])
            ? $resolved["table"] . "." . $resolved["column"]
            : $resolved["column"];

        $direction =
            strtoupper(
                $order["direction"] ?? "ASC"
            );

        $orders[] =
            $columnName
            . " "
            . $direction;

    }

    $offset =
        (int)($column["offset"] ?? 1);

    $sql =
        "LEAD("
        . $resolvedColumn
        . ", "
        . $offset;

    if (array_key_exists("default", $column)) {

        $default =
            $this->buildValue(
                $column["default"]
            );

        $sql .=
            ", "
            . $default;

    }

    $sql .=
        ") OVER (ORDER BY "
        . implode(", ", $orders)
        . ") AS ["
        . $alias
        . "]";

    $selectColumns[] = $sql;

    continue;

}

/*
 * FIRST_VALUE()
 */
if ($function == "FIRST_VALUE") {

    $orders = [];

    foreach ($column["orderBy"] as $order) {

        $resolved =
            $this->resolveColumn(
                $order["column"]
            );

        $columnName =
            !empty($resolved["table"])
            ? $resolved["table"] . "." . $resolved["column"]
            : $resolved["column"];

        $direction =
            strtoupper(
                $order["direction"] ?? "ASC"
            );

        $orders[] =
            $columnName
            . " "
            . $direction;

    }

    $selectColumns[] =
        "FIRST_VALUE("
        . $resolvedColumn
        . ") OVER (ORDER BY "
        . implode(", ", $orders)
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * LAST_VALUE()
 */
if ($function == "LAST_VALUE") {

    $orders = [];

    foreach ($column["orderBy"] as $order) {

        $resolved =
            $this->resolveColumn(
                $order["column"]
            );

        $columnName =
            !empty($resolved["table"])
            ? $resolved["table"] . "." . $resolved["column"]
            : $resolved["column"];

        $direction =
            strtoupper(
                $order["direction"] ?? "ASC"
            );

        $orders[] =
            $columnName
            . " "
            . $direction;

    }

    $selectColumns[] =
        "LAST_VALUE("
        . $resolvedColumn
        . ") OVER (ORDER BY "
        . implode(", ", $orders)
        . " ROWS BETWEEN UNBOUNDED PRECEDING "
        . "AND UNBOUNDED FOLLOWING"
        . ") AS ["
        . $alias
        . "]";

    continue;

}

/*
 * STRING_AGG()
 */
if ($function == "STRING_AGG") {

    $separator =
        str_replace(
            "'",
            "''",
            $column["separator"]
        );

    $sql =
        "STRING_AGG("
        . $resolvedColumn
        . ", '"
        . $separator
        . "')";

    if (!empty($column["orderBy"])) {

        $orders = [];

        foreach ($column["orderBy"] as $order) {

            $resolved =
                $this->resolveColumn(
                    $order["column"]
                );

            $columnName =
                !empty($resolved["table"])
                ? $resolved["table"] . "." . $resolved["column"]
                : $resolved["column"];

            $direction =
                strtoupper(
                    $order["direction"] ?? "ASC"
                );

            $orders[] =
                $columnName
                . " "
                . $direction;

        }

        $sql .=
            " WITHIN GROUP (ORDER BY "
            . implode(", ", $orders)
            . ")";

    }

    $sql .=
        " AS ["
        . $alias
        . "]";

    $selectColumns[] = $sql;

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
        if (!empty($request['where'])) {

            foreach ($request['where'] as $filter) {

                /*
                * EXISTS / NOT EXISTS has no column
                */
                if (
                    strtoupper($filter['operator']) == "EXISTS"
                    || strtoupper($filter['operator']) == "NOT EXISTS"
                   ) {
                    continue;
                }

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

            foreach ($request['where'] as $filter) {

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
                    "IS NOT NULL",
                    "EXISTS",
                    "NOT EXISTS",
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

/*
* EXISTS / NOT EXISTS
*/

if (
    strtoupper($filter['operator']) == "EXISTS"
    ||
    strtoupper($filter['operator']) == "NOT EXISTS"
)
{

    if (!isset($filter['subquery']))
    {
        throw new Exception(
            "{$filter['operator']} requires a subquery."
        );
    }

    $subQuery =
        $this->buildSelect(
            $filter['subquery'],
            true
        );

    $conditions[] =
        "{$filter['operator']} ({$subQuery['sql']})";

    $params = array_merge(
        $params,
        $subQuery['params']
    );

    continue;

}

                if (
                    strtoupper($filter['operator']) == "IN"
                    || strtoupper($filter['operator']) == "NOT IN"
                ) {

            /*
             * Subquery
             */
            if (isset($filter['subquery'])) {

                $subQuery =
                    $this->buildSelect(
                        $filter['subquery'],
                        true
                    );

                $conditions[] =
                    "{$filter['column']} {$filter['operator']} ({$subQuery['sql']})";

                $params = array_merge(
                    $params,
                    $subQuery['params']
                );

                continue;

            }

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
             "MAX",
             "STRING_AGG",
        ];

        $stringFunctions = [
            "UPPER",
            "LOWER",
            "LTRIM",
            "RTRIM",
            "TRIM",
            "LEN",

            "LEFT",
            "RIGHT",
            "SUBSTRING",

            "REPLACE",

            "CHARINDEX",

            "PATINDEX",

            "FORMAT"
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
    if (!$isUnion) {

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

    }

/*
 * Total Rows
 *
 * Calculate total matching rows before pagination.
 */
$countSql = null;

if (
    isset($request['page']) &&
    isset($request['pageSize'])
) {
    $countBaseSql = $sql;

    /*
     * Remove ORDER BY from count query.
     */
    $countBaseSql = preg_replace(
        '/\s+ORDER BY\s+.*$/is',
        '',
        $countBaseSql
    );

    $countSql =
        "SELECT COUNT(*) AS TotalRows
         FROM (
             {$countBaseSql}
         ) AS CountQuery";

    $countResult =
        $this->queryEngine->executePrepared(
            $countSql,
            $params
        );

    if (
        !empty($countResult['data']) &&
        isset($countResult['data'][0]['TotalRows'])
    ) {
        $totalRows =
            (int)$countResult['data'][0]['TotalRows'];
    } else {
        $totalRows = 0;
    }
}

/*
 * Pagination
 *
 * SQL Server compatibility level 110+
 *     -> OFFSET / FETCH
 *
 * Older compatibility levels
 *     -> ROW_NUMBER()
 */
if (
    isset($request['page']) &&
    isset($request['pageSize'])
) {

    $page =
        max(1, (int)$request['page']);

    $pageSize =
        max(1, (int)$request['pageSize']);

    $offset =
        ($page - 1) * $pageSize;

    $compatibilityLevel =
        $this->getSqlServerCompatibilityLevel();

    /*
     * Modern SQL Server
     *
     * Compatibility level 110+
     */
    if ($compatibilityLevel >= 110) {

        $sql .= "
            OFFSET {$offset} ROWS
            FETCH NEXT {$pageSize} ROWS ONLY
        ";

    }

    /*
     * Legacy SQL Server
     *
     * Compatibility level below 110
     */
    else {

        /*
         * The query already contains ORDER BY.
         *
         * Remove the ORDER BY from the inner query
         * and use it inside ROW_NUMBER().
         */
        $orderBy = "ORDER BY 1";

        if (!empty($request['sort'])) {

            $orders = [];

            foreach ($request['sort'] as $sort) {

                $column =
                    $sort['column'];

                $direction =
                    strtoupper(
                        $sort['direction'] ?? "ASC"
                    );

                $orders[] =
                    $column
                    . " "
                    . $direction;
            }

            if (!empty($orders)) {

                $orderBy =
                    "ORDER BY "
                    . implode(", ", $orders);
            }
        }
        elseif (!empty($request['groupBy'])) {

            $orderBy =
                "ORDER BY "
                . $request['groupBy'][0];
        }

        /*
         * Remove the existing ORDER BY.
         */
        $innerSql =
            preg_replace(
                '/\s+ORDER BY\s+.*$/is',
                '',
                $sql
            );

        $startRow =
            $offset + 1;

        $endRow =
            $offset + $pageSize;

        $sql = "
            SELECT *
            FROM
            (
                SELECT
                    PagedSource.*,
                    ROW_NUMBER() OVER (
                        {$orderBy}
                    ) AS __row_num
                FROM
                (
                    {$innerSql}
                ) AS PagedSource
            ) AS PagedQuery
            WHERE __row_num
                BETWEEN {$startRow}
                AND {$endRow}
            ORDER BY __row_num
        ";
    }
}
        
        $sql = $cteSql . $sql;

        return [
            'sql' => $sql,
            'params' => $params,
            'totalRows' => $totalRows
        ];
    }

    /*
     * Build Procedure Query
     */
    public function buildProcedure(array $request)
    {
        if (empty($request["procedure"])) {
            throw new Exception("Procedure name is required.");
        }

        $params = $request["params"] ?? [];

        $placeholders = "";

        if (count($params) > 0) {
            $placeholders = implode(
                ", ",
                array_fill(0, count($params), "?")
            );
        }

        $sql = "EXEC {$request['procedure']}";

        if ($placeholders !== "") {
            $sql .= " " . $placeholders;
        }

        return [
            "sql" => $sql,
            "params" => $params
        ];
    }

    /*
     * Execute Function
     */
    public function buildFunction(array $request)
    {
        if (empty($request["function"])) {
            throw new Exception("Function name is required.");
        }

        $params = $request["params"] ?? [];

        $placeholders = "";

        if (count($params) > 0) {
            $placeholders = implode(
                ", ",
                array_fill(0, count($params), "?")
            );
        }

        $sql = "SELECT {$request['function']}(";

        if ($placeholders !== "") {
            $sql .= $placeholders;
        }

        $sql .= ") AS Result";

        return [
            "sql" => $sql,
            "params" => $params
        ];
    }

    /*
     * Execute Table-Valued Function
     */
    public function buildTableFunction(array $request)
    {
        if (empty($request["function"])) {
            throw new Exception("Function name is required.");
        }

        $params = $request["params"] ?? [];

        $placeholders = "";

        if (count($params) > 0) {
            $placeholders = implode(
                ", ",
                array_fill(0, count($params), "?")
            );
        }

        $sql = "SELECT * FROM {$request['function']}(";

        if ($placeholders !== "") {
            $sql .= $placeholders;
        }

        $sql .= ")";

        return [
            "sql" => $sql,
            "params" => $params
        ];
    }
    
}