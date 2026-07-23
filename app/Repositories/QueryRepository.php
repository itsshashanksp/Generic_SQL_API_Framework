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
 * CAST / CONVERT Validation
 */
if (
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
        "MAX"
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

        "FORMAT"
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
        "DATEFROMPARTS"
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