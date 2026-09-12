<?php

require_once __DIR__ . '/ApiRequestException.php';

class WritePayloadValidator
{
    public function validate(array $request, array $resource, array $metadataRows): array
    {
        $metadata = $this->indexMetadata($metadataRows, $resource);
        $this->validateResourceMetadata($resource, $metadata);

        $validated = $request;
        if (isset($request['data'])) {
            $validated['data'] = $this->validateData(
                $request['data'],
                $resource,
                $metadata,
                $request['action']
            );
        }
        if (isset($request['filters'])) {
            $validated['filters'] = $this->validateFilters(
                $request['filters'],
                $resource,
                $metadata
            );
        }
        if ($request['action'] === 'upsert') {
            $validated['keys'] = $this->validateKeys(
                $request['keys'] ?? null,
                $validated['data'],
                $resource,
                $metadata
            );
        }
        return $validated;
    }

    private function indexMetadata(array $rows, array $resource): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $column = $this->rowValue($row, 'ColumnName');
            if (!is_string($column) || $column === '') continue;
            $indexed[strtolower($column)] = [
                'name' => $column,
                'type' => strtolower((string)$this->rowValue($row, 'DataType')),
                'maxLength' => (int)$this->rowValue($row, 'MaxLength'),
                'precision' => (int)$this->rowValue($row, 'NumericPrecision'),
                'scale' => (int)$this->rowValue($row, 'NumericScale'),
                'nullable' => (bool)$this->rowValue($row, 'IsNullable'),
                'identity' => (bool)$this->rowValue($row, 'IsIdentity'),
                'computed' => (bool)$this->rowValue($row, 'IsComputed'),
                'generated' => (int)$this->rowValue($row, 'GeneratedAlwaysType') !== 0,
                'hidden' => (bool)$this->rowValue($row, 'IsHidden'),
                'hasDefault' => (bool)$this->rowValue($row, 'HasDefault'),
            ];
        }
        if ($indexed === []) {
            throw new RuntimeException(
                "Configured write target is unavailable: {$resource['resource']}"
            );
        }
        return $indexed;
    }

    private function validateResourceMetadata(array $resource, array $metadata): void
    {
        foreach (array_merge($resource['columns'], $resource['filterColumns']) as $column) {
            if (!isset($metadata[strtolower($column)])) {
                throw new RuntimeException(
                    "Configured write column is unavailable: {$resource['resource']}"
                );
            }
        }
        $identity = $resource['identityColumn'];
        if ($identity !== null) {
            $column = $metadata[strtolower($identity)] ?? null;
            if ($column === null || !$column['identity']) {
                throw new RuntimeException(
                    "Configured identity column is not an identity: {$resource['resource']}"
                );
            }
        }
    }

    private function validateData(array $data, array $resource, array $metadata, string $action): array
    {
        $canonical = [];
        foreach ($data as $requestedColumn => $value) {
            $column = $this->allowedColumn($requestedColumn, $resource['columns']);
            if ($column === null) {
                $this->invalidColumn('data.' . $requestedColumn, 'Column is not writable for this resource.');
            }
            $properties = $metadata[strtolower($column)];
            if ($properties['identity'] || $properties['computed']
                || $properties['generated'] || $properties['hidden']
                || in_array($properties['type'], ['timestamp', 'rowversion'], true)) {
                $this->invalidColumn('data.' . $requestedColumn, 'Database-generated columns cannot be written.');
            }
            if (array_key_exists($properties['name'], $canonical)) {
                $this->invalidColumn('data.' . $requestedColumn, 'Column was supplied more than once.');
            }
            $canonical[$properties['name']] = $this->validateValue(
                $value,
                $properties,
                'data.' . $requestedColumn
            );
        }

        if (in_array($action, ['insert', 'upsert'], true)) {
            foreach ($metadata as $properties) {
                $required = !$properties['nullable'] && !$properties['identity']
                    && !$properties['computed'] && !$properties['generated']
                    && !$properties['hidden'] && !$properties['hasDefault']
                    && !in_array($properties['type'], ['timestamp', 'rowversion'], true);
                if (!$required) continue;
                $allowed = $this->allowedColumn($properties['name'], $resource['columns']);
                if ($allowed === null) {
                    throw new RuntimeException(
                        "Write resource omits a required target column: {$resource['resource']}"
                    );
                }
                if (!array_key_exists($properties['name'], $canonical)) {
                    throw new ApiRequestException(
                        'Missing required field.',
                        'MISSING_REQUIRED_FIELD',
                        [['path' => 'data.' . $allowed, 'message' => 'This database field is required.']]
                    );
                }
            }
        }
        return $canonical;
    }

    private function validateFilters(array $filters, array $resource, array $metadata): array
    {
        $validated = [];
        foreach ($filters as $index => $filter) {
            $requestedField = $filter['field'];
            $field = $this->allowedColumn($requestedField, $resource['filterColumns']);
            if ($field === null) {
                $this->invalidColumn("filters.{$index}.field", 'Column is not filterable for this resource.');
            }
            $properties = $metadata[strtolower($field)];
            $operator = strtoupper($filter['operator']);
            $item = ['field' => $properties['name'], 'operator' => $operator];
            if (array_key_exists('value', $filter)) {
                $values = in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'], true)
                    ? $filter['value']
                    : [$filter['value']];
                $normalized = [];
                foreach ($values as $valueIndex => $value) {
                    if ($value === null) {
                        throw new ApiRequestException(
                            'Invalid write value.',
                            'INVALID_WRITE_VALUE',
                            [[
                                'path' => "filters.{$index}.value" . (count($values) > 1 ? ".{$valueIndex}" : ''),
                                'message' => 'Use IS NULL or IS NOT NULL for null filters.',
                            ]]
                        );
                    }
                    $normalized[] = $this->validateValue(
                        $value,
                        $properties,
                        "filters.{$index}.value" . (count($values) > 1 ? ".{$valueIndex}" : '')
                    );
                }
                $item['value'] = in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'], true)
                    ? $normalized
                    : $normalized[0];
            }
            $validated[] = $item;
        }
        return $validated;
    }

    private function validateKeys(?array $requestedKeys, array $data, array $resource, array $metadata): array
    {
        if ($resource['keys'] === []) {
            throw new ApiRequestException(
                'Invalid UPSERT key.',
                'INVALID_UPSERT_KEY',
                [['path' => 'keys', 'message' => 'UPSERT is not enabled for this resource.']]
            );
        }
        if ($requestedKeys !== null) {
            $requested = array_map('strtolower', $requestedKeys);
            $configured = array_map('strtolower', $resource['keys']);
            sort($requested);
            sort($configured);
            if ($requested !== $configured) {
                throw new ApiRequestException(
                    'Invalid UPSERT key.',
                    'INVALID_UPSERT_KEY',
                    [['path' => 'keys', 'message' => 'Keys must exactly match the resource UPSERT key set.']]
                );
            }
        }
        $canonical = [];
        foreach ($resource['keys'] as $key) {
            $name = $metadata[strtolower($key)]['name'];
            if (!array_key_exists($name, $data) || $data[$name] === null) {
                throw new ApiRequestException(
                    'Invalid UPSERT key.',
                    'INVALID_UPSERT_KEY',
                    [['path' => 'data.' . $key, 'message' => 'A non-null value is required for each UPSERT key.']]
                );
            }
            $canonical[] = $name;
        }
        if (count($data) === count($canonical)) {
            throw new ApiRequestException(
                'Invalid write value.',
                'INVALID_WRITE_VALUE',
                [['path' => 'data', 'message' => 'UPSERT requires at least one non-key value for the update path.']]
            );
        }
        return $canonical;
    }

    private function validateValue($value, array $column, string $path)
    {
        if ($value === null) {
            if (!$column['nullable']) {
                $this->invalidValue($path, 'This field does not accept null.');
            }
            return null;
        }

        $type = $column['type'];
        if (in_array($type, ['tinyint', 'smallint', 'int', 'bigint'], true)) {
            if (!is_int($value)) $this->invalidValue($path, 'Expected an integer.');
            $ranges = [
                'tinyint' => [0, 255], 'smallint' => [-32768, 32767],
                'int' => [-2147483648, 2147483647],
            ];
            if (isset($ranges[$type]) && ($value < $ranges[$type][0] || $value > $ranges[$type][1])) {
                $this->invalidValue($path, 'Integer is outside the database type range.');
            }
            return $value;
        }
        if (in_array($type, ['decimal', 'numeric', 'money', 'smallmoney', 'float', 'real'], true)) {
            if (!is_int($value) && !is_float($value)
                && !(is_string($value) && preg_match('/^-?(?:\d+)(?:\.\d+)?$/', $value) === 1)) {
                $this->invalidValue($path, 'Expected a numeric value.');
            }
            return $value;
        }
        if ($type === 'bit') {
            if (!is_bool($value) && $value !== 0 && $value !== 1) {
                $this->invalidValue($path, 'Expected a boolean or 0/1.');
            }
            return is_bool($value) ? (int)$value : $value;
        }
        if (in_array($type, ['char', 'varchar', 'nchar', 'nvarchar', 'text', 'ntext', 'xml'], true)) {
            if (!is_string($value)) $this->invalidValue($path, 'Expected a string.');
            $maxLength = $column['maxLength'];
            if (in_array($type, ['nchar', 'nvarchar'], true) && $maxLength > 0) $maxLength = intdiv($maxLength, 2);
            if (in_array($type, ['char', 'varchar', 'nchar', 'nvarchar'], true)
                && $maxLength > 0 && $this->stringLength($value) > $maxLength) {
                $this->invalidValue($path, "String exceeds the {$maxLength}-character limit.");
            }
            return $value;
        }
        if (in_array($type, ['date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset', 'time'], true)) {
            if (!is_string($value) || !$this->isTemporalValue($value, $type)) {
                $this->invalidValue($path, 'Expected an ISO date/time string.');
            }
            return $value;
        }
        if ($type === 'uniqueidentifier') {
            if (!is_string($value)
                || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) !== 1) {
                $this->invalidValue($path, 'Expected a UUID value.');
            }
            return $value;
        }
        if (in_array($type, ['binary', 'varbinary', 'image'], true)) {
            if (!is_string($value)) $this->invalidValue($path, 'Expected a binary string value.');
            return $value;
        }

        $this->invalidValue($path, 'This database type is not supported for writes.');
    }

    private function isTemporalValue(string $value, string $type): bool
    {
        if ($type === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        }
        if ($type === 'time') {
            return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,7})?)?$/', $value) === 1;
        }
        if (preg_match(
            '/^\d{4}-\d{2}-\d{2}[T ](?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,7})?(?:Z|[+-]\d{2}:\d{2})?$/',
            $value
        ) !== 1) return false;
        [$year, $month, $day] = array_map('intval', explode('-', substr($value, 0, 10)));
        return checkdate($month, $day, $year);
    }

    private function allowedColumn(string $requested, array $allowed): ?string
    {
        foreach ($allowed as $column) {
            if (strcasecmp($column, $requested) === 0) return $column;
        }
        return null;
    }

    private function rowValue(array $row, string $key)
    {
        foreach ($row as $name => $value) {
            if (strcasecmp((string)$name, $key) === 0) return $value;
        }
        return null;
    }

    private function stringLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function invalidColumn(string $path, string $message): never
    {
        throw new ApiRequestException(
            'Invalid write column.',
            'INVALID_WRITE_COLUMN',
            [['path' => $path, 'message' => $message]]
        );
    }

    private function invalidValue(string $path, string $message): never
    {
        throw new ApiRequestException(
            'Invalid write value.',
            'INVALID_WRITE_VALUE',
            [['path' => $path, 'message' => $message]]
        );
    }
}
