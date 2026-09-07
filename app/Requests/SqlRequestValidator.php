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
        $this->rejectUnknown(
            $request,
            ['action', 'resource', 'filters', 'sort', 'pagination', 'filterLogic'],
            $errors
        );

        if (($request['action'] ?? null) !== 'sql') {
            $errors[] = ['path' => 'action', 'message' => 'SQL action is required.'];
        }
        if (!is_string($request['resource'] ?? null)
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $request['resource'])) {
            $errors[] = ['path' => 'resource', 'message' => 'A valid SQL resource identifier is required.'];
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

        if (isset($request['sort'])) {
            if (!is_array($request['sort'])) {
                $errors[] = ['path' => 'sort', 'message' => 'Sort must be an array.'];
            } else {
                foreach ($request['sort'] as $index => $sort) {
                    $path = "sort.{$index}";
                    if (!is_array($sort)) {
                        $errors[] = ['path' => $path, 'message' => 'Sort entry must be an object.'];
                        continue;
                    }
                    $this->rejectUnknown($sort, ['field', 'direction'], $errors, $path . '.');
                    if (!$this->isIdentifier($sort['field'] ?? null)) {
                        $errors[] = ['path' => $path . '.field', 'message' => 'Field must be a valid identifier.'];
                    }
                    if (!in_array(strtoupper((string)($sort['direction'] ?? '')), ['ASC', 'DESC'], true)) {
                        $errors[] = ['path' => $path . '.direction', 'message' => 'Direction must be ASC or DESC.'];
                    }
                }
            }
        }

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

        if ($errors !== []) {
            throw new ApiRequestException('Invalid request.', 'INVALID_REQUEST', $errors);
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
}
