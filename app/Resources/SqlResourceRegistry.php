<?php

require_once __DIR__ . '/SqlResourceDiscovery.php';

class SqlResourceRegistry
{
    private SqlResourceDiscovery $discovery;

    public function __construct(?array $settings = null, ?string $resourceRoot = null)
    {
        $this->discovery = new SqlResourceDiscovery($settings, $resourceRoot);
    }

    public function resolve(string $resource, array $execution = []): array
    {
        $discovered = $this->discovery->resolve($resource);
        return [
            'resource' => $discovered['id'],
            'file' => $discovered['file'],
            ...$this->executionDefinition($execution),
        ];
    }

    public function discoverResourceIds(): array
    {
        return $this->discovery->ids();
    }

    private function executionDefinition(array $execution): array
    {
        if (array_diff(array_keys($execution), ['columns', 'filters', 'defaultSort']) !== []) {
            throw new RuntimeException('Invalid SQL execution metadata.');
        }
        $columns = $execution['columns'] ?? [];
        if (array_key_exists('columns', $execution) && !$this->isIdentifierList($columns)) {
            throw new RuntimeException('Invalid SQL execution columns.');
        }
        $defaultSort = $execution['defaultSort'] ?? [];
        if (!is_array($defaultSort)
            || (array_key_exists('defaultSort', $execution)
                && ($defaultSort === [] || !array_is_list($defaultSort)))) {
            throw new RuntimeException('Invalid SQL execution default sort.');
        }
        foreach ($defaultSort as $sort) {
            if (!is_array($sort)
                || !$this->contains($columns, $sort['field'] ?? null)
                || !in_array(strtoupper((string)($sort['direction'] ?? '')), ['ASC', 'DESC'], true)) {
                throw new RuntimeException('Invalid SQL execution default sort.');
            }
        }

        $filters = [];
        foreach ($columns as $column) {
            $filters[strtolower($column)] = $this->filter($column, $column, 'output', null, false);
        }
        $mappings = $execution['filters'] ?? [];
        if (array_key_exists('filters', $execution)
            && (!is_array($mappings) || $mappings === [] || array_is_list($mappings))) {
            throw new RuntimeException('Invalid SQL execution filter metadata.');
        }
        $seen = [];
        foreach ($mappings as $field => $mapping) {
            if (!$this->isIdentifier($field) || !is_array($mapping)
                || ($mapping !== [] && array_is_list($mapping))
                || array_diff(array_keys($mapping), ['expression', 'valueType', 'placement']) !== []) {
                throw new RuntimeException('Invalid SQL execution filter metadata.');
            }
            $canonical = strtolower($field);
            if (isset($seen[$canonical])) {
                throw new RuntimeException('Invalid SQL execution filter metadata.');
            }
            $seen[$canonical] = true;
            $placement = $mapping['placement'] ?? 'output';
            $expression = $mapping['expression'] ?? $field;
            $valueType = $mapping['valueType'] ?? null;
            $valid = $placement === 'output'
                ? $this->isIdentifier($expression) && $this->contains($columns, $expression)
                : ($placement === 'source'
                    ? is_string($expression) && $this->isQualifiedIdentifier($expression)
                    : is_string($expression) && $this->isAggregate($expression));
            if (!in_array($placement, ['output', 'source', 'having'], true)
                || !$valid || ($valueType !== null && $valueType !== 'integer-date')) {
                throw new RuntimeException('Invalid SQL execution filter metadata.');
            }
            $filters[$canonical] = $this->filter(
                $field,
                trim($expression),
                $placement === 'source' ? 'where' : $placement,
                $valueType,
                $placement !== 'output'
            );
        }
        return ['columns' => $columns, 'filters' => $filters, 'defaultSort' => $defaultSort];
    }

    private function filter(string $field, string $expression, string $location, ?string $valueType, bool $mapped): array
    {
        return compact('field', 'expression', 'location', 'valueType') + ['mappedExpression' => $mapped];
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

    private function isIdentifier($value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    private function isQualifiedIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $value) === 1;
    }

    private function isAggregate(string $value): bool
    {
        return preg_match('/^(?:COUNT|SUM|AVG|MIN|MAX)\s*\(\s*(?:\*|[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*)\s*\)$/i', trim($value)) === 1;
    }

    private function contains(array $values, $expected): bool
    {
        if (!is_string($expected)) return false;
        foreach ($values as $value) {
            if (is_string($value) && strcasecmp($value, $expected) === 0) return true;
        }
        return false;
    }
}
