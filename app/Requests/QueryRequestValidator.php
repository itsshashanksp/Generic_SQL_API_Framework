<?php

require_once __DIR__ . '/ApiRequestException.php';

class QueryRequestValidator
{
    private const ACTIONS = [
        'select', 'union', 'unionAll', 'procedure', 'function', 'tableFunction',
        'metadata.tables', 'metadata.columns', 'metadata.views',
        'metadata.procedures', 'metadata.schema'
    ];

    private const OPERATORS = [
        '=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN',
        'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'IS NULL', 'IS NOT NULL',
        'EXISTS', 'NOT EXISTS'
    ];

    private const FUNCTIONS = [
        'COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'STRING_AGG',
        'UPPER', 'LOWER', 'LTRIM', 'RTRIM', 'TRIM', 'LEN', 'COALESCE',
        'ISNULL', 'CAST', 'CONVERT', 'NULLIF', 'CONCAT', 'LEFT', 'RIGHT',
        'SUBSTRING', 'REPLACE', 'CHARINDEX', 'PATINDEX', 'FORMAT', 'CHOOSE',
        'YEAR', 'MONTH', 'DAY', 'DATEPART', 'DATENAME', 'GETDATE', 'DATEADD',
        'DATEDIFF', 'EOMONTH', 'ISDATE', 'DATEFROMPARTS',
        'DATETIMEFROMPARTS', 'TIMEFROMPARTS', 'SYSDATETIME',
        'CURRENT_TIMESTAMP', 'IIF', 'ABS', 'ROUND', 'CEILING', 'FLOOR',
        'POWER', 'SQRT', 'EXP', 'LOG', 'ROW_NUMBER', 'RANK', 'DENSE_RANK',
        'NTILE', 'LAG', 'LEAD', 'FIRST_VALUE', 'LAST_VALUE'
    ];

    private const FIELD_KEYS = [
        'field', 'fields', 'function', 'alias', 'sort', 'case', 'expression',
        'buckets', 'offset', 'default', 'separator', 'datatype', 'style',
        'value', 'values', 'index', 'datepart', 'number', 'start', 'end',
        'year', 'month', 'day', 'hour', 'minute', 'second', 'millisecond',
        'precision', 'power', 'part', 'length', 'search', 'replace',
        'pattern', 'format', 'condition', 'true', 'false'
    ];

    public function validate(array $request): void
    {
        $errors = [];
        $action = $request['action'] ?? null;
        if (!is_string($action) || !in_array($action, self::ACTIONS, true)) {
            $errors[] = ['path' => 'action', 'message' => 'A supported action is required.'];
        } elseif ($action === 'select') {
            $this->validateSelect($request, '', $errors);
        } elseif ($action === 'union' || $action === 'unionAll') {
            $this->rejectUnknown($request, ['action', 'queries'], '', $errors);
            if (empty($request['queries']) || !is_array($request['queries'])) {
                $errors[] = ['path' => 'queries', 'message' => 'At least one query is required.'];
            } else {
                foreach ($request['queries'] as $index => $query) {
                    if (!is_array($query)) {
                        $errors[] = ['path' => "queries.{$index}", 'message' => 'Query must be an object.'];
                        continue;
                    }
                    $query['action'] = 'select';
                    $this->validateSelect($query, "queries.{$index}.", $errors);
                }
            }
        } elseif (in_array($action, ['procedure', 'function', 'tableFunction'], true)) {
            $this->rejectUnknown($request, ['action', 'source', 'parameters'], '', $errors);
            $key = $action === 'procedure' ? 'procedure' : 'function';
            $this->validateSourceName($request, $key, $errors);
            if (isset($request['parameters']) && !is_array($request['parameters'])) {
                $errors[] = ['path' => 'parameters', 'message' => 'Parameters must be an array.'];
            }
        } elseif (str_starts_with($action, 'metadata.')) {
            if ($action === 'metadata.columns') {
                $this->rejectUnknown($request, ['action', 'source'], '', $errors);
                $this->validateSourceName($request, 'table', $errors);
            } else {
                $this->rejectUnknown($request, ['action'], '', $errors);
            }
        }

        if ($errors !== []) {
            throw new ApiRequestException('Invalid request.', 'INVALID_REQUEST', $errors);
        }
    }

    private function validateSelect(array $request, string $prefix, array &$errors): void
    {
        $this->rejectUnknown($request, [
            'action', 'source', 'fields', 'filters', 'joins', 'groupBy',
            'having', 'sort', 'pagination', 'distinct', 'limit',
            'filterLogic', 'with'
        ], $prefix, $errors);
        $this->validateSourceName($request, 'table', $errors, $prefix);
        if (empty($request['fields']) || !is_array($request['fields'])) {
            $errors[] = ['path' => $prefix . 'fields', 'message' => 'Fields must be a non-empty array.'];
        } else {
            foreach ($request['fields'] as $index => $field) {
                $path = $prefix . "fields.{$index}";
                if (is_string($field)) {
                    if ($field !== '*' && !$this->isIdentifier($field)) {
                        $errors[] = ['path' => $path, 'message' => 'Field must be a valid identifier.'];
                    }
                } elseif (!is_array($field)) {
                    $errors[] = ['path' => $path, 'message' => 'Field must be a string or object.'];
                } else {
                    $this->rejectUnknown($field, self::FIELD_KEYS, $path . '.', $errors);
                    if (isset($field['function'])
                        && (!is_string($field['function'])
                            || !in_array(strtoupper($field['function']), self::FUNCTIONS, true))) {
                        $errors[] = ['path' => $path . '.function', 'message' => 'Unsupported function.'];
                    }
                    if (isset($field['field']) && $field['field'] !== '*'
                        && (!is_string($field['field']) || !$this->isIdentifier($field['field']))) {
                        $errors[] = ['path' => $path . '.field', 'message' => 'Field must be a valid identifier.'];
                    }
                    if (isset($field['alias']) && !$this->isIdentifier($field['alias'])) {
                        $errors[] = ['path' => $path . '.alias', 'message' => 'Alias must be a valid identifier.'];
                    }
                    if (!isset($field['field']) && !isset($field['function'])
                        && !isset($field['case']) && !isset($field['expression'])) {
                        $errors[] = ['path' => $path, 'message' => 'Field object requires field, function, case, or expression.'];
                    }
                    if (isset($field['fields'])
                        && (!is_array($field['fields'])
                            || array_filter($field['fields'], fn ($item) => !$this->isIdentifier($item)))) {
                        $errors[] = ['path' => $path . '.fields', 'message' => 'Function fields must be valid identifiers.'];
                    }
                    if (isset($field['sort'])) {
                        $this->validateSort($field['sort'], $path . '.sort', $errors);
                    }
                    if (isset($field['function'])
                        && in_array(strtoupper((string)$field['function']), [
                            'ROW_NUMBER', 'RANK', 'DENSE_RANK', 'NTILE', 'LAG',
                            'LEAD', 'FIRST_VALUE', 'LAST_VALUE'
                        ], true)
                        && !isset($field['sort'])) {
                        $errors[] = ['path' => $path . '.sort', 'message' => 'Window functions require sort.'];
                    }
                    if (isset($field['datatype'])
                        && (!is_string($field['datatype'])
                            || preg_match('/^[A-Za-z]+(?:\([0-9]+(?:,[0-9]+)?\))?$/', $field['datatype']) !== 1)) {
                        $errors[] = ['path' => $path . '.datatype', 'message' => 'Datatype is invalid.'];
                    }
                    if (isset($field['expression'])) {
                        $this->validateExpression($field['expression'], $path . '.expression', $errors);
                    }
                    if (isset($field['case'])) {
                        $this->validateCase($field['case'], $path . '.case', $errors);
                    }
                }
            }
        }

        if (isset($request['filters'])) {
            $this->validateFilters($request['filters'], $prefix . 'filters', $errors);
        }
        if (isset($request['sort'])) {
            $this->validateSort($request['sort'], $prefix . 'sort', $errors);
        }
        if (isset($request['pagination'])) {
            $pagination = $request['pagination'];
            if (!is_array($pagination)) {
                $errors[] = ['path' => $prefix . 'pagination', 'message' => 'Pagination must be an object.'];
            } else {
                $this->rejectUnknown($pagination, ['page', 'pageSize'], $prefix . 'pagination.', $errors);
                foreach (['page', 'pageSize'] as $key) {
                    if (!isset($pagination[$key]) || !is_int($pagination[$key]) || $pagination[$key] < 1) {
                        $errors[] = ['path' => $prefix . "pagination.{$key}", 'message' => 'Must be a positive integer.'];
                    }
                }
            }
        }
        if (isset($request['groupBy'])
            && (!is_array($request['groupBy'])
                || array_filter($request['groupBy'], fn ($field) => !is_string($field) || !$this->isIdentifier($field)))) {
            $errors[] = ['path' => $prefix . 'groupBy', 'message' => 'Group fields must be valid identifiers.'];
        }
        if (isset($request['joins'])) {
            if (!is_array($request['joins'])) {
                $errors[] = ['path' => $prefix . 'joins', 'message' => 'Joins must be an array.'];
            } else {
                foreach ($request['joins'] as $index => $join) {
                    $path = $prefix . "joins.{$index}";
                    if (!is_array($join) || !in_array(strtoupper((string)($join['type'] ?? '')), ['INNER', 'LEFT', 'RIGHT'], true)) {
                        $errors[] = ['path' => $path . '.type', 'message' => 'Join type must be INNER, LEFT, or RIGHT.'];
                    }
                    if (!is_array($join)) { continue; }
                    $this->rejectUnknown($join, ['type', 'source', 'on'], $path . '.', $errors);
                    $this->validateSourceName($join, 'table', $errors, $path . '.');
                    $on = $join['on'] ?? null;
                    if (!is_array($on) || ($on['operator'] ?? '=') !== '='
                        || !$this->isIdentifier($on['left'] ?? null)
                        || !$this->isIdentifier($on['right'] ?? null)) {
                        $errors[] = ['path' => $path . '.on', 'message' => 'Join on requires valid left/right fields and the = operator.'];
                    } else {
                        $this->rejectUnknown($on, ['left', 'operator', 'right'], $path . '.on.', $errors);
                    }
                }
            }
        }
        if (isset($request['having'])) {
            if (!is_array($request['having'])) {
                $errors[] = ['path' => $prefix . 'having', 'message' => 'Having must be an array.'];
            } else {
                foreach ($request['having'] as $index => $condition) {
                    $path = $prefix . "having.{$index}";
                    if (is_array($condition)) {
                        $this->rejectUnknown($condition, ['function', 'field', 'operator', 'value'], $path . '.', $errors);
                    }
                    if (!is_array($condition)
                        || !in_array(strtoupper((string)($condition['function'] ?? '')), ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'STRING_AGG'], true)
                        || (($condition['field'] ?? null) !== '*' && !$this->isIdentifier($condition['field'] ?? null))
                        || !in_array(strtoupper((string)($condition['operator'] ?? '')), ['=', '!=', '<>', '>', '<', '>=', '<='], true)
                        || !array_key_exists('value', $condition)) {
                        $errors[] = ['path' => $path, 'message' => 'Invalid aggregate HAVING condition.'];
                    }
                }
            }
        }
        if (isset($request['limit']) && (!is_int($request['limit']) || $request['limit'] < 1)) {
            $errors[] = ['path' => $prefix . 'limit', 'message' => 'Limit must be a positive integer.'];
        }
        if (isset($request['distinct']) && !is_bool($request['distinct'])) {
            $errors[] = ['path' => $prefix . 'distinct', 'message' => 'Distinct must be a boolean.'];
        }
        if (isset($request['filterLogic']) && !in_array($request['filterLogic'], ['AND', 'OR'], true)) {
            $errors[] = ['path' => $prefix . 'filterLogic', 'message' => 'Filter logic must be AND or OR.'];
        }
        if (isset($request['with'])) {
            $this->validateWith($request['with'], $prefix . 'with', $errors);
        }
    }

    private function validateFilters($filters, string $path, array &$errors): void
    {
        if (!is_array($filters)) {
            $errors[] = ['path' => $path, 'message' => 'Filters must be an array.'];
            return;
        }
        foreach ($filters as $index => $filter) {
            $itemPath = "{$path}.{$index}";
            if (!is_array($filter)) {
                $errors[] = ['path' => $itemPath, 'message' => 'Filter must be an object.'];
                continue;
            }
            $this->rejectUnknown($filter, ['field', 'operator', 'value', 'query'], $itemPath . '.', $errors);
            $operator = strtoupper((string)($filter['operator'] ?? ''));
            if (!in_array($operator, self::OPERATORS, true)) {
                $errors[] = ['path' => $itemPath . '.operator', 'message' => 'Unsupported filter operator.'];
                continue;
            }
            if (!in_array($operator, ['EXISTS', 'NOT EXISTS'], true)
                && !$this->isIdentifier($filter['field'] ?? null)) {
                $errors[] = ['path' => $itemPath . '.field', 'message' => 'Field must be a valid identifier.'];
            }
            if (in_array($operator, ['EXISTS', 'NOT EXISTS'], true)) {
                if (!is_array($filter['query'] ?? null)) {
                    $errors[] = ['path' => $itemPath . '.query', 'message' => 'A subquery is required.'];
                } else {
                    $this->validateSelect($filter['query'], $itemPath . '.query.', $errors);
                }
                continue;
            }
            if (in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
                if (isset($filter['query'])) {
                    $errors[] = ['path' => $itemPath . '.query', 'message' => 'This operator does not support a subquery.'];
                }
                continue;
            }
            if (in_array($operator, ['IN', 'NOT IN'], true)) {
                if (isset($filter['query'])) {
                    if (!is_array($filter['query'])) {
                        $errors[] = ['path' => $itemPath . '.query', 'message' => 'Subquery must be an object.'];
                    } else {
                        $this->validateSelect($filter['query'], $itemPath . '.query.', $errors);
                    }
                } elseif (!isset($filter['value']) || !is_array($filter['value']) || $filter['value'] === []) {
                    $errors[] = ['path' => $itemPath . '.value', 'message' => 'IN requires a non-empty value array or query.'];
                }
                continue;
            }
            if (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)) {
                if (!isset($filter['value']) || !is_array($filter['value']) || count($filter['value']) !== 2) {
                    $errors[] = ['path' => $itemPath . '.value', 'message' => 'BETWEEN requires exactly two values.'];
                }
                if (isset($filter['query'])) {
                    $errors[] = ['path' => $itemPath . '.query', 'message' => 'BETWEEN does not support a subquery.'];
                }
                continue;
            }
            if (!array_key_exists('value', $filter)) {
                $errors[] = ['path' => $itemPath . '.value', 'message' => 'A value is required.'];
            }
            if (isset($filter['query'])) {
                $errors[] = ['path' => $itemPath . '.query', 'message' => 'This operator does not support a subquery.'];
            }
        }
    }

    private function validateWith($with, string $path, array &$errors): void
    {
        if (!is_array($with)) {
            $errors[] = ['path' => $path, 'message' => 'With must be an object.'];
            return;
        }
        if (!$this->isIdentifier($with['name'] ?? null)) {
            $errors[] = ['path' => $path . '.name', 'message' => 'CTE name must be a valid identifier.'];
        }

        $isRecursive = array_key_exists('anchor', $with) || array_key_exists('recursive', $with);
        $this->rejectUnknown(
            $with,
            $isRecursive ? ['name', 'anchor', 'recursive'] : ['name', 'query'],
            $path . '.',
            $errors
        );
        $branches = $isRecursive ? ['anchor', 'recursive'] : ['query'];
        foreach ($branches as $branch) {
            if (!is_array($with[$branch] ?? null)) {
                $errors[] = ['path' => $path . '.' . $branch, 'message' => 'CTE branch must be a SELECT body.'];
                continue;
            }
            $this->validateSelect($with[$branch], $path . '.' . $branch . '.', $errors);
        }
    }

    private function validateSort($sort, string $path, array &$errors): void
    {
        if (!is_array($sort)) {
            $errors[] = ['path' => $path, 'message' => 'Sort must be an array.'];
            return;
        }
        foreach ($sort as $index => $item) {
            if (is_array($item)) {
                $this->rejectUnknown($item, ['field', 'direction'], "{$path}.{$index}.", $errors);
            }
            if (!is_array($item) || !$this->isIdentifier($item['field'] ?? null)
                || ctype_digit((string)($item['field'] ?? ''))
                || !in_array(strtoupper((string)($item['direction'] ?? 'ASC')), ['ASC', 'DESC'], true)) {
                $errors[] = ['path' => "{$path}.{$index}", 'message' => 'Sort requires a logical field and ASC or DESC direction.'];
            }
        }
    }

    private function validateExpression($expression, string $path, array &$errors): void
    {
        if (!is_array($expression)) {
            $errors[] = ['path' => $path, 'message' => 'Expression must be an object.'];
            return;
        }
        $this->rejectUnknown($expression, ['left', 'operator', 'right'], $path . '.', $errors);
        if (!in_array($expression['operator'] ?? null, ['+', '-', '*', '/', '%'], true)) {
            $errors[] = ['path' => $path . '.operator', 'message' => 'Unsupported expression operator.'];
        }
        foreach (['left', 'right'] as $side) {
            $value = $expression[$side] ?? null;
            if (!is_int($value) && !is_float($value) && !$this->isIdentifier($value)) {
                $errors[] = ['path' => $path . '.' . $side, 'message' => 'Expression operand must be a number or field.'];
            }
        }
    }

    private function validateCase($case, string $path, array &$errors): void
    {
        if (!is_array($case)) {
            $errors[] = ['path' => $path, 'message' => 'Case must be an object.'];
            return;
        }
        $this->rejectUnknown($case, ['when', 'else'], $path . '.', $errors);
        if (empty($case['when']) || !is_array($case['when'])) {
            $errors[] = ['path' => $path . '.when', 'message' => 'Case requires at least one WHEN clause.'];
            return;
        }
        foreach ($case['when'] as $index => $when) {
            $itemPath = $path . '.when.' . $index;
            if (!is_array($when) || !is_array($when['condition'] ?? null)) {
                $errors[] = ['path' => $itemPath, 'message' => 'Case WHEN requires a condition and then value.'];
                continue;
            }
            $this->rejectUnknown($when, ['condition', 'then'], $itemPath . '.', $errors);
            $condition = $when['condition'];
            $this->rejectUnknown($condition, ['field', 'operator', 'value'], $itemPath . '.condition.', $errors);
            if (!$this->isIdentifier($condition['field'] ?? null)
                || !in_array(strtoupper((string)($condition['operator'] ?? '')), ['=', '!=', '<>', '>', '<', '>=', '<='], true)
                || !array_key_exists('value', $condition)
                || !array_key_exists('then', $when)) {
                $errors[] = ['path' => $itemPath, 'message' => 'Invalid CASE WHEN clause.'];
            }
        }
    }

    private function validateSourceName(array $request, string $key, array &$errors, string $prefix = ''): void
    {
        $source = $request['source'] ?? null;
        if (!is_array($source) || !$this->isIdentifier($source[$key] ?? null)) {
            $errors[] = ['path' => $prefix . "source.{$key}", 'message' => 'A valid source identifier is required.'];
            return;
        }
        $this->rejectUnknown($source, [$key, 'alias'], $prefix . 'source.', $errors);
        if (isset($source['alias']) && !$this->isIdentifier($source['alias'])) {
            $errors[] = ['path' => $prefix . 'source.alias', 'message' => 'Alias must be a valid identifier.'];
        }
    }

    private function isIdentifier($value): bool
    {
        return is_string($value)
            && preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $value) === 1;
    }

    private function rejectUnknown(
        array $value,
        array $allowed,
        string $prefix,
        array &$errors
    ): void {
        foreach (array_keys($value) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = ['path' => $prefix . $key, 'message' => 'Unknown property.'];
            }
        }
    }
}
