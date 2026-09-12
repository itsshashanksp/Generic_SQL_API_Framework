<?php

require_once __DIR__ . '/ApiRequestException.php';

class WriteRequestValidator
{
    private const ACTIONS = ['insert', 'update', 'delete', 'upsert'];
    private const OPERATORS = [
        '=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE',
        'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'IS NULL', 'IS NOT NULL'
    ];

    public function validate(array $request): void
    {
        $errors = [];
        $action = $request['action'] ?? null;
        if (!is_string($action) || !in_array($action, self::ACTIONS, true)) {
            $errors[] = ['path' => 'action', 'message' => 'A supported write action is required.'];
        }

        $allowed = match ($action) {
            'insert' => ['action', 'resource', 'data'],
            'update' => ['action', 'resource', 'data', 'filters', 'filterLogic'],
            'delete' => ['action', 'resource', 'filters', 'filterLogic'],
            'upsert' => ['action', 'resource', 'data', 'keys'],
            default => ['action'],
        };
        $this->rejectUnknown($request, $allowed, '', $errors);

        if (!is_string($request['resource'] ?? null)
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $request['resource']) !== 1) {
            $errors[] = ['path' => 'resource', 'message' => 'A valid resource identifier is required.'];
        }

        if (in_array($action, ['insert', 'update', 'upsert'], true)) {
            $this->validateData($request['data'] ?? null, $errors);
        }
        if (in_array($action, ['update', 'delete'], true)) {
            if (!array_key_exists('filters', $request) || $request['filters'] === []) {
                throw new ApiRequestException(
                    'Unsafe write rejected.',
                    'UNSAFE_WRITE',
                    [['path' => 'filters', 'message' => 'UPDATE and DELETE require a non-empty targeting condition.']]
                );
            }
            $this->validateFilters($request['filters'] ?? null, $errors);
            if (isset($request['filterLogic'])
                && !in_array($request['filterLogic'], ['AND', 'OR'], true)) {
                $errors[] = ['path' => 'filterLogic', 'message' => 'Filter logic must be AND or OR.'];
            }
        }
        if ($action === 'upsert' && array_key_exists('keys', $request)) {
            $keys = $request['keys'];
            if (!is_array($keys) || $keys === [] || !array_is_list($keys)) {
                $errors[] = ['path' => 'keys', 'message' => 'UPSERT keys must be a non-empty array.'];
            } else {
                $seen = [];
                foreach ($keys as $index => $key) {
                    if (!$this->isColumn($key)) {
                        $errors[] = ['path' => "keys.{$index}", 'message' => 'Key must be a valid column identifier.'];
                    } elseif (isset($seen[strtolower($key)])) {
                        $errors[] = ['path' => "keys.{$index}", 'message' => 'UPSERT keys must be unique.'];
                    }
                    if (is_string($key)) $seen[strtolower($key)] = true;
                }
            }
        }

        if ($errors !== []) {
            throw new ApiRequestException('Invalid request.', 'INVALID_REQUEST', $errors);
        }
    }

    private function validateData($data, array &$errors): void
    {
        if (!is_array($data) || $data === [] || array_is_list($data)) {
            $errors[] = ['path' => 'data', 'message' => 'Data must be a non-empty object.'];
            return;
        }
        foreach ($data as $column => $value) {
            if (!$this->isColumn($column)) {
                $errors[] = ['path' => 'data.' . $column, 'message' => 'Column name is invalid.'];
            }
            if (!$this->isScalarOrNull($value)) {
                $errors[] = ['path' => 'data.' . $column, 'message' => 'Write values must be scalar or null.'];
            }
        }
    }

    private function validateFilters($filters, array &$errors): void
    {
        if (!is_array($filters) || $filters === [] || !array_is_list($filters)) {
            $errors[] = [
                'path' => 'filters',
                'message' => 'A non-empty filter array is required for this write action.',
            ];
            return;
        }
        foreach ($filters as $index => $filter) {
            $path = "filters.{$index}";
            if (!is_array($filter) || array_is_list($filter)) {
                $errors[] = ['path' => $path, 'message' => 'Filter must be an object.'];
                continue;
            }
            $this->rejectUnknown($filter, ['field', 'operator', 'value'], $path . '.', $errors);
            if (!$this->isColumn($filter['field'] ?? null)) {
                $errors[] = ['path' => $path . '.field', 'message' => 'Filter field must be a valid column identifier.'];
            }
            $operator = strtoupper((string)($filter['operator'] ?? ''));
            if (!in_array($operator, self::OPERATORS, true)) {
                $errors[] = ['path' => $path . '.operator', 'message' => 'Unsupported write filter operator.'];
                continue;
            }
            $hasValue = array_key_exists('value', $filter);
            if (in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
                if ($hasValue) {
                    $errors[] = ['path' => $path . '.value', 'message' => 'NULL operators do not accept a value.'];
                }
            } elseif (!$hasValue) {
                $errors[] = ['path' => $path . '.value', 'message' => 'Filter value is required.'];
            } elseif (in_array($operator, ['IN', 'NOT IN'], true)) {
                if (!is_array($filter['value']) || !array_is_list($filter['value']) || $filter['value'] === []
                    || array_filter($filter['value'], fn ($value) => !$this->isScalarOrNull($value))) {
                    $errors[] = ['path' => $path . '.value', 'message' => "{$operator} requires a non-empty scalar array."];
                }
            } elseif (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)) {
                if (!is_array($filter['value']) || !array_is_list($filter['value']) || count($filter['value']) !== 2
                    || array_filter($filter['value'], fn ($value) => !$this->isScalarOrNull($value))) {
                    $errors[] = ['path' => $path . '.value', 'message' => "{$operator} requires exactly two scalar values."];
                }
            } elseif (!$this->isScalarOrNull($filter['value'])) {
                $errors[] = ['path' => $path . '.value', 'message' => 'Filter value must be scalar or null.'];
            }
        }
    }

    private function isColumn($value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    private function isScalarOrNull($value): bool
    {
        return is_string($value) || is_int($value) || is_float($value)
            || is_bool($value) || $value === null;
    }

    private function rejectUnknown(array $value, array $allowed, string $prefix, array &$errors): void
    {
        foreach (array_keys($value) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = ['path' => $prefix . $key, 'message' => 'Unknown property.'];
            }
        }
    }
}
