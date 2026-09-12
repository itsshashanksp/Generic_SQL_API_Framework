<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../Requests/ApiRequestException.php';

class WriteResourceRegistry
{
    private array $resources;

    public function __construct(?array $resources = null)
    {
        $this->resources = $resources ?? require ROOT_PATH . '/config/write-resources.php';
    }

    public function resolve(string $resource, ?string $action = null): array
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $resource) !== 1
            || !array_key_exists($resource, $this->resources)) {
            throw new ApiRequestException(
                'Invalid write resource.',
                'INVALID_WRITE_RESOURCE',
                [['path' => 'resource', 'message' => 'Resource is not approved for writes.']]
            );
        }

        $definition = $this->resources[$resource];
        if (!is_array($definition)) {
            throw new RuntimeException("Invalid write resource registry entry: {$resource}");
        }
        if (array_diff(array_keys($definition), [
            'schema', 'table', 'actions', 'columns', 'filterColumns', 'keys', 'identityColumn'
        ]) !== []) {
            throw new RuntimeException("Unknown write resource registry setting: {$resource}");
        }
        $schema = $definition['schema'] ?? 'dbo';
        $table = $definition['table'] ?? null;
        $actions = $definition['actions'] ?? null;
        $columns = $definition['columns'] ?? null;
        $filterColumns = $definition['filterColumns'] ?? $columns;
        $keys = $definition['keys'] ?? [];
        $identityColumn = $definition['identityColumn'] ?? null;

        if (!$this->isIdentifier($schema) || !$this->isIdentifier($table)
            || !is_array($actions) || !array_is_list($actions) || $actions === []
            || array_filter($actions, fn ($item) => !in_array($item, ['insert', 'update', 'delete', 'upsert'], true)) !== []
            || !$this->isIdentifierList($columns, false)
            || !$this->isIdentifierList($filterColumns, false)
            || !$this->isIdentifierList($keys, true)
            || ($identityColumn !== null && !$this->isIdentifier($identityColumn))) {
            throw new RuntimeException("Invalid write resource registry entry: {$resource}");
        }

        $columns = array_values($columns);
        $actions = array_values($actions);
        $filterColumns = array_values($filterColumns);
        $keys = array_values($keys);
        $this->assertUnique($columns, $resource, 'columns');
        $this->assertUnique($actions, $resource, 'actions');
        $this->assertUnique($filterColumns, $resource, 'filterColumns');
        $this->assertUnique($keys, $resource, 'keys');
        foreach ($keys as $key) {
            if (!$this->contains($columns, $key)) {
                throw new RuntimeException("Invalid write resource keys: {$resource}");
            }
        }
        if ($identityColumn !== null
            && !$this->contains(array_merge($columns, $filterColumns), $identityColumn)) {
            throw new RuntimeException("Invalid write resource identity column: {$resource}");
        }
        if (in_array('upsert', $actions, true) && $keys === []) {
            throw new RuntimeException("UPSERT resource requires configured keys: {$resource}");
        }
        if ($action !== null && !in_array($action, $actions, true)) {
            throw new ApiRequestException(
                'Invalid write resource.',
                'INVALID_WRITE_RESOURCE',
                [['path' => 'resource', 'message' => 'Resource is not approved for this write action.']]
            );
        }

        return [
            'resource' => $resource,
            'schema' => $schema,
            'table' => $table,
            'actions' => $actions,
            'columns' => $columns,
            'filterColumns' => $filterColumns,
            'keys' => $keys,
            'identityColumn' => $identityColumn,
        ];
    }

    private function isIdentifier($value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    private function isIdentifierList($value, bool $allowEmpty): bool
    {
        return is_array($value) && array_is_list($value)
            && ($allowEmpty || $value !== [])
            && array_filter($value, fn ($item) => !$this->isIdentifier($item)) === [];
    }

    private function assertUnique(array $values, string $resource, string $field): void
    {
        if (count(array_unique(array_map('strtolower', $values))) !== count($values)) {
            throw new RuntimeException("Duplicate write resource {$field}: {$resource}");
        }
    }

    private function contains(array $values, string $needle): bool
    {
        return in_array(strtolower($needle), array_map('strtolower', $values), true);
    }
}
