<?php

require_once __DIR__ . '/ApiRequestException.php';

class SqlRequestValidator
{
    private const OPERATORS = [
        '=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE',
        'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'IS NULL', 'IS NOT NULL',
    ];

    public function validate(array $request): void
    {
        $errors = [];
        $this->rejectUnknown($request, [
            'action', 'resource', 'execution', 'filters', 'sort', 'pagination', 'filterLogic',
        ], $errors);
        if (($request['action'] ?? null) !== 'sql') {
            $errors[] = ['path' => 'action', 'message' => 'SQL action is required.'];
        }
        if (!is_string($request['resource'] ?? null)
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*(?:\/[A-Za-z0-9][A-Za-z0-9_-]*)*$/', $request['resource']) !== 1) {
            $errors[] = ['path' => 'resource', 'message' => 'A valid SQL resource identifier is required.'];
        }
        if (isset($request['execution'])) {
            $this->validateExecution($request['execution'], $errors);
        }
        if (isset($request['filters'])) {
            if (!is_array($request['filters'])) {
                $errors[] = ['path' => 'filters', 'message' => 'Filters must be an array.'];
            } else {
                foreach ($request['filters'] as $index => $filter) {
                    $this->validateFilter($filter, "filters.{$index}", $errors);
                }
            }
        }
        if (isset($request['sort'])) $this->validateSort($request['sort'], 'sort', $errors);
        if (isset($request['pagination'])) {
            $pagination = $request['pagination'];
            if (!is_array($pagination)) {
                $errors[] = ['path' => 'pagination', 'message' => 'Pagination must be an object.'];
            } else {
                $this->rejectUnknown($pagination, ['page', 'pageSize'], $errors, 'pagination.');
                foreach (['page', 'pageSize'] as $key) {
                    if (!isset($pagination[$key]) || !is_int($pagination[$key]) || $pagination[$key] < 1) {
                        $errors[] = ['path' => "pagination.{$key}", 'message' => 'Must be a positive integer.'];
                    }
                }
            }
        }
        if (isset($request['filterLogic'])
            && !in_array(strtoupper((string)$request['filterLogic']), ['AND', 'OR'], true)) {
            $errors[] = ['path' => 'filterLogic', 'message' => 'Filter logic must be AND or OR.'];
        }
        if ($errors !== []) throw new ApiRequestException('Invalid request.', 'INVALID_REQUEST', $errors);
    }

    private function validateExecution($execution, array &$errors): void
    {
        if (!is_array($execution) || array_is_list($execution)) {
            $errors[] = ['path' => 'execution', 'message' => 'Execution metadata must be an object.'];
            return;
        }
        $this->rejectUnknown($execution, ['columns', 'filters', 'defaultSort'], $errors, 'execution.');
        $columns = $execution['columns'] ?? null;
        if ($columns !== null && !$this->isIdentifierList($columns)) {
            $errors[] = ['path' => 'execution.columns', 'message' => 'Execution columns must be a non-empty unique identifier array.'];
        }
        if (isset($execution['filters'])) {
            $filters = $execution['filters'];
            if (!is_array($filters) || $filters === [] || array_is_list($filters)) {
                $errors[] = ['path' => 'execution.filters', 'message' => 'Execution filters must be a non-empty object.'];
            } else {
                $seen = [];
                foreach ($filters as $field => $definition) {
                    $path = 'execution.filters.' . $field;
                    if (!$this->isIdentifier($field)) {
                        $errors[] = ['path' => $path, 'message' => 'Filter name must be a valid identifier.'];
                        continue;
                    }
                    if (isset($seen[strtolower($field)])) {
                        $errors[] = ['path' => $path, 'message' => 'Execution filter names must be unique.'];
                    }
                    $seen[strtolower($field)] = true;
                    $this->validateExecutionFilter($definition, $field, $columns, $path, $errors);
                }
            }
        }
        if (isset($execution['defaultSort'])) {
            $this->validateSort($execution['defaultSort'], 'execution.defaultSort', $errors);
            if ($execution['defaultSort'] === []) {
                $errors[] = ['path' => 'execution.defaultSort', 'message' => 'Default sort must not be empty when supplied.'];
            }
            if ($columns === null) {
                $errors[] = ['path' => 'execution.columns', 'message' => 'Execution columns are required with defaultSort.'];
            } elseif (is_array($columns) && is_array($execution['defaultSort'])) {
                foreach ($execution['defaultSort'] as $index => $sort) {
                    if (is_array($sort) && isset($sort['field'])
                        && !$this->containsCaseInsensitive($columns, $sort['field'])) {
                        $errors[] = [
                            'path' => "execution.defaultSort.{$index}.field",
                            'message' => 'Default sort field must be an execution output column.',
                        ];
                    }
                }
            }
        }
    }

    private function validateExecutionFilter($definition, string $field, $columns, string $path, array &$errors): void
    {
        if (!is_array($definition) || ($definition !== [] && array_is_list($definition))) {
            $errors[] = ['path' => $path, 'message' => 'Execution filter metadata must be an object.'];
            return;
        }
        $this->rejectUnknown($definition, ['expression', 'valueType', 'placement'], $errors, $path . '.');
        $placement = $definition['placement'] ?? 'output';
        if (!in_array($placement, ['output', 'source', 'having'], true)) {
            $errors[] = ['path' => $path . '.placement', 'message' => 'Placement must be output, source, or having.'];
            return;
        }
        $expression = $definition['expression'] ?? null;
        if ($placement === 'output') {
            $expression = $expression ?? $field;
            if (!$this->isIdentifier($expression)) {
                $errors[] = ['path' => $path . '.expression', 'message' => 'Output filter expression must be an output identifier.'];
            } elseif (!is_array($columns) || !$this->containsCaseInsensitive($columns, $expression)) {
                $errors[] = ['path' => $path . '.expression', 'message' => 'Output filter expression must be an execution output column.'];
            }
        } elseif ($placement === 'source') {
            if (!is_string($expression) || !$this->isQualifiedIdentifier($expression)) {
                $errors[] = ['path' => $path . '.expression', 'message' => 'Source filter expression must be an optionally qualified identifier.'];
            }
        } elseif (!is_string($expression) || !$this->isSafeAggregateExpression($expression)) {
            $errors[] = [
                'path' => $path . '.expression',
                'message' => 'HAVING filter expression must be a supported aggregate over one identifier.',
            ];
        }
        if (isset($definition['valueType']) && $definition['valueType'] !== 'integer-date') {
            $errors[] = ['path' => $path . '.valueType', 'message' => 'Unsupported execution filter value type.'];
        }
    }

    private function validateFilter($filter, string $path, array &$errors): void
    {
        if (!is_array($filter)) {
            $errors[] = ['path' => $path, 'message' => 'Filter must be an object.'];
            return;
        }
        $this->rejectUnknown($filter, ['field', 'operator', 'value'], $errors, $path . '.');
        if (!$this->isIdentifier($filter['field'] ?? null)) {
            $errors[] = ['path' => $path . '.field', 'message' => 'Field must be a valid identifier.'];
        }
        $operator = strtoupper((string)($filter['operator'] ?? ''));
        if (!in_array($operator, self::OPERATORS, true)) {
            $errors[] = ['path' => $path . '.operator', 'message' => 'Unsupported operator.'];
            return;
        }
        if (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)
            && (!isset($filter['value']) || !is_array($filter['value']) || count($filter['value']) !== 2)) {
            $errors[] = ['path' => $path . '.value', 'message' => 'BETWEEN requires exactly two values.'];
        } elseif (in_array($operator, ['IN', 'NOT IN'], true)
            && (!isset($filter['value']) || !is_array($filter['value']) || $filter['value'] === [])) {
            $errors[] = ['path' => $path . '.value', 'message' => 'IN requires at least one value.'];
        } elseif (!in_array($operator, ['IS NULL', 'IS NOT NULL'], true)
            && !array_key_exists('value', $filter)) {
            $errors[] = ['path' => $path . '.value', 'message' => 'Filter value is required.'];
        } elseif (!in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'IS NULL', 'IS NOT NULL'], true)
            && is_array($filter['value'])) {
            $errors[] = ['path' => $path . '.value', 'message' => 'Filter value must be scalar or null.'];
        }
    }

    private function validateSort($sort, string $path, array &$errors): void
    {
        if (!is_array($sort)) {
            $errors[] = ['path' => $path, 'message' => 'Sort must be an array.'];
            return;
        }
        foreach ($sort as $index => $entry) {
            $entryPath = "{$path}.{$index}";
            if (!is_array($entry)) {
                $errors[] = ['path' => $entryPath, 'message' => 'Sort entry must be an object.'];
                continue;
            }
            $this->rejectUnknown($entry, ['field', 'direction'], $errors, $entryPath . '.');
            if (!$this->isIdentifier($entry['field'] ?? null)) {
                $errors[] = ['path' => $entryPath . '.field', 'message' => 'Field must be a valid identifier.'];
            }
            if (!in_array(strtoupper((string)($entry['direction'] ?? '')), ['ASC', 'DESC'], true)) {
                $errors[] = ['path' => $entryPath . '.direction', 'message' => 'Direction must be ASC or DESC.'];
            }
        }
    }

    private function rejectUnknown(array $value, array $allowed, array &$errors, string $prefix = ''): void
    {
        foreach (array_keys($value) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = ['path' => $prefix . $key, 'message' => 'Unknown property.'];
            }
        }
    }

    private function isIdentifier($value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    private function isIdentifierList($value): bool
    {
        if (!is_array($value) || $value === [] || !array_is_list($value)) return false;
        $seen = [];
        foreach ($value as $item) {
            if (!$this->isIdentifier($item) || isset($seen[strtolower($item)])) return false;
            $seen[strtolower($item)] = true;
        }
        return true;
    }

    private function containsCaseInsensitive(array $values, string $expected): bool
    {
        foreach ($values as $value) {
            if (is_string($value) && strcasecmp($value, $expected) === 0) return true;
        }
        return false;
    }

    private function isQualifiedIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $value) === 1;
    }

    private function isSafeAggregateExpression(string $value): bool
    {
        return preg_match(
            '/^(?:COUNT|SUM|AVG|MIN|MAX)\s*\(\s*(?:\*|[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*)\s*\)$/i',
            trim($value)
        ) === 1;
    }
}
