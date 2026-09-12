<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../Requests/ApiRequestException.php';

class SqlResourceRegistry
{
    public const RUNTIME_FILTER_MARKER = '/*__RUNTIME_FILTERS__*/';

    private array $resources;

    public function __construct(?array $resources = null)
    {
        $this->resources = $resources ?? require ROOT_PATH . '/config/sql-resources.php';
    }

    public function resolve(string $resource): array
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $resource)
            || !isset($this->resources[$resource])) {
            throw new ApiRequestException(
                'Invalid SQL resource.',
                'INVALID_SQL_RESOURCE',
                [['path' => 'resource', 'message' => 'Resource is not approved.']]
            );
        }

        $definition = $this->resources[$resource];
        if (!is_array($definition)
            || empty($definition['file'])
            || empty($definition['columns'])
            || !is_array($definition['columns'])
            || empty($definition['defaultSort'])
            || !is_array($definition['defaultSort'])) {
            throw new RuntimeException("Invalid SQL resource registry entry: {$resource}");
        }
        foreach ($definition['columns'] as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new RuntimeException("Invalid SQL resource column: {$resource}");
            }
        }
        [$filterColumns, $filterValueTypes, $filterPlacement, $filters] =
            array_key_exists('filters', $definition)
                ? $this->resolveMappedFilters($definition, $resource)
                : $this->resolveLegacyFilters($definition, $resource);
        foreach ($definition['defaultSort'] ?? [] as $sort) {
            if (!is_array($sort)
                || !in_array($sort['field'] ?? null, $definition['columns'], true)
                || !in_array(strtoupper((string)($sort['direction'] ?? '')), ['ASC', 'DESC'], true)) {
                throw new RuntimeException("Invalid SQL resource default sort: {$resource}");
            }
        }

        $realFile = realpath($definition['file']);
        $resourceRoot = realpath(QUERY_PATH);
        if ($realFile === false
            || $resourceRoot === false
            || !str_starts_with($realFile, $resourceRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("SQL resource file is unavailable: {$resource}");
        }

        $sql = file_get_contents($realFile);
        $markerCount = $sql === false
            ? 0
            : substr_count($sql, self::RUNTIME_FILTER_MARKER);
        if (($filterPlacement === 'source' && $markerCount !== 1)
            || (in_array($filterPlacement, ['output', 'mapped'], true) && $markerCount !== 0)) {
            throw new RuntimeException("Invalid SQL resource runtime filter marker: {$resource}");
        }

        return [
            'file' => $realFile,
            'columns' => array_values($definition['columns']),
            'filterColumns' => array_values($filterColumns),
            'filterValueTypes' => $filterValueTypes,
            'filterPlacement' => $filterPlacement,
            'filters' => $filters,
            'defaultSort' => array_values($definition['defaultSort'] ?? []),
        ];
    }

    private function resolveLegacyFilters(array $definition, string $resource): array
    {
        $filterColumns = $definition['filterColumns'] ?? $definition['columns'];
        if (!is_array($filterColumns) || $filterColumns === []) {
            throw new RuntimeException("Invalid SQL resource filter columns: {$resource}");
        }
        foreach ($filterColumns as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new RuntimeException("Invalid SQL resource filter column: {$resource}");
            }
        }
        $filterValueTypes = [];
        foreach ($definition['filterValueTypes'] ?? [] as $column => $type) {
            $canonicalColumn = array_values(array_filter(
                $filterColumns,
                fn (string $filterColumn): bool => strcasecmp($filterColumn, (string)$column) === 0
            ))[0] ?? null;
            if ($canonicalColumn === null || $type !== 'integer-date') {
                throw new RuntimeException("Invalid SQL resource filter value type: {$resource}");
            }
            $filterValueTypes[strtolower($canonicalColumn)] = $type;
        }
        $filterPlacement = $definition['filterPlacement'] ?? 'output';
        if (!in_array($filterPlacement, ['output', 'source'], true)) {
            throw new RuntimeException("Invalid SQL resource filter placement: {$resource}");
        }

        $filters = [];
        foreach ($filterColumns as $column) {
            $filters[strtolower($column)] = [
                'field' => $column,
                'expression' => $column,
                'location' => $filterPlacement,
                'valueType' => $filterValueTypes[strtolower($column)] ?? null,
                'mappedExpression' => false,
            ];
        }

        return [array_values($filterColumns), $filterValueTypes, $filterPlacement, $filters];
    }

    private function resolveMappedFilters(array $definition, string $resource): array
    {
        foreach (['filterColumns', 'filterValueTypes', 'filterPlacement'] as $legacyField) {
            if (array_key_exists($legacyField, $definition)) {
                throw new RuntimeException("Mapped and legacy SQL resource filters cannot be combined: {$resource}");
            }
        }
        if (!is_array($definition['filters']) || $definition['filters'] === []) {
            throw new RuntimeException("Invalid SQL resource filter mapping: {$resource}");
        }

        $filters = [];
        $filterColumns = [];
        $filterValueTypes = [];
        foreach ($definition['filters'] as $field => $mapping) {
            if (!is_string($field)
                || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) !== 1
                || !is_array($mapping)
                || array_diff(array_keys($mapping), ['expression', 'location', 'valueType']) !== []) {
                throw new RuntimeException("Invalid SQL resource filter mapping: {$resource}");
            }
            $expression = $mapping['expression'] ?? null;
            $location = $mapping['location'] ?? null;
            $valueType = $mapping['valueType'] ?? null;
            if (!is_string($expression)
                || trim($expression) === ''
                || preg_match('/[?;\x00]|--|\/\*|\*\//', $expression) === 1
                || !in_array($location, ['output', 'where', 'having'], true)
                || ($location === 'output'
                    && (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) !== 1
                        || !$this->containsCaseInsensitive($definition['columns'], $expression)))
                || ($valueType !== null && $valueType !== 'integer-date')) {
                throw new RuntimeException("Invalid SQL resource filter mapping: {$resource}");
            }

            $canonical = strtolower($field);
            if (isset($filters[$canonical])) {
                throw new RuntimeException("Duplicate SQL resource filter mapping: {$resource}");
            }
            $filterColumns[] = $field;
            if ($valueType !== null) {
                $filterValueTypes[$canonical] = $valueType;
            }
            $filters[$canonical] = [
                'field' => $field,
                'expression' => trim($expression),
                'location' => $location,
                'valueType' => $valueType,
                'mappedExpression' => true,
            ];
        }

        return [$filterColumns, $filterValueTypes, 'mapped', $filters];
    }

    private function containsCaseInsensitive(array $values, string $expected): bool
    {
        foreach ($values as $value) {
            if (is_string($value) && strcasecmp($value, $expected) === 0) {
                return true;
            }
        }
        return false;
    }
}
