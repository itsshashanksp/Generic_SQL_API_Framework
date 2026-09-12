<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../Requests/ApiRequestException.php';

class SqlResourceRegistry
{
    public const RUNTIME_FILTER_MARKER = '/*__RUNTIME_FILTERS__*/';
    public const SETTINGS_KEY = '__settings';

    private array $legacyResources;
    private string $resourceRoot;
    private array $excludedDirectories;
    private ?array $discoveredResources = null;

    public function __construct(?array $resources = null, ?string $resourceRoot = null)
    {
        $configuration = $resources;
        if ($configuration === null) {
            $configFile = ROOT_PATH . '/config/sql-resources.php';
            $configuration = is_file($configFile) ? require $configFile : [];
        }
        if (!is_array($configuration)) {
            throw new RuntimeException('Invalid SQL resource configuration.');
        }

        $settings = $configuration[self::SETTINGS_KEY] ?? [];
        unset($configuration[self::SETTINGS_KEY]);
        if (!is_array($settings)
            || array_diff(array_keys($settings), ['root', 'exclude']) !== []) {
            throw new RuntimeException('Invalid SQL resource discovery settings.');
        }
        $configuredRoot = $resourceRoot ?? ($settings['root'] ?? QUERY_PATH);
        $resolvedRoot = is_string($configuredRoot) ? realpath($configuredRoot) : false;
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new RuntimeException('SQL resource root is unavailable.');
        }
        $excluded = $settings['exclude'] ?? ['system'];
        if (!is_array($excluded) || !array_is_list($excluded)
            || array_filter($excluded, fn ($item) => !$this->isPathSegment($item)) !== []) {
            throw new RuntimeException('Invalid SQL resource discovery exclusions.');
        }

        $this->legacyResources = $configuration;
        $this->resourceRoot = rtrim($resolvedRoot, DIRECTORY_SEPARATOR);
        $this->excludedDirectories = array_map('strtolower', $excluded);
    }

    public function resolve(string $resource, array $execution = []): array
    {
        if (!$this->isResourceIdentifier($resource)) {
            $this->invalidResource();
        }

        $discovered = $this->discover();
        if (array_key_exists($resource, $this->legacyResources)) {
            if (isset($discovered[$resource])) {
                throw new RuntimeException("Duplicate SQL resource identity: {$resource}");
            }
            $definition = $this->resolveLegacy($resource, $this->legacyResources[$resource]);
        } else {
            $discoveredResource = $discovered[$resource] ?? null;
            if ($discoveredResource === null && !str_contains($resource, '/')) {
                $matches = array_values(array_filter(
                    $discovered,
                    fn (array $item): bool => $item['basename'] === $resource
                ));
                if (count($matches) > 1) {
                    throw new ApiRequestException(
                        'Ambiguous SQL resource.',
                        'INVALID_SQL_RESOURCE',
                        [['path' => 'resource', 'message' => 'Use the full relative resource identifier.']]
                    );
                }
                $discoveredResource = $matches[0] ?? null;
            }
            if ($discoveredResource === null) $this->invalidResource();
            $definition = $this->resolveDiscovered($discoveredResource);
        }

        return $execution === [] ? $definition : $this->applyExecutionMetadata($definition, $execution);
    }

    public function discoverResourceIds(): array
    {
        $ids = array_keys($this->discover());
        sort($ids, SORT_STRING);
        return $ids;
    }

    private function discover(): array
    {
        if ($this->discoveredResources !== null) return $this->discoveredResources;

        $resources = [];
        $caseInsensitiveIds = [];
        $physicalFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->resourceRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'sql') continue;
            $realFile = realpath($file->getPathname());
            if ($realFile === false || !$this->isInsideRoot($realFile)) continue;
            $relative = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($realFile, strlen($this->resourceRoot) + 1)
            );
            $segments = explode('/', $relative);
            array_pop($segments);
            if ($this->isExcludedPath($segments)) continue;
            $id = substr($relative, 0, -4);
            if (!$this->isResourceIdentifier($id)) continue;

            $canonicalId = strtolower($id);
            if (isset($caseInsensitiveIds[$canonicalId]) || isset($physicalFiles[$realFile])) {
                throw new RuntimeException("Duplicate SQL resource identity: {$id}");
            }
            $caseInsensitiveIds[$canonicalId] = true;
            $physicalFiles[$realFile] = true;
            $resources[$id] = [
                'id' => $id,
                'basename' => basename($id),
                'file' => $realFile,
            ];
        }
        ksort($resources, SORT_STRING);
        return $this->discoveredResources = $resources;
    }

    private function resolveDiscovered(array $resource): array
    {
        return [
            'resource' => $resource['id'],
            'file' => $resource['file'],
            'columns' => [],
            'filterColumns' => [],
            'filterValueTypes' => [],
            'filterPlacement' => 'mapped',
            'filters' => [],
            'defaultSort' => [],
            'discovered' => true,
        ];
    }

    private function resolveLegacy(string $resource, $definition): array
    {
        if (!is_array($definition)
            || empty($definition['file'])
            || empty($definition['columns'])
            || !is_array($definition['columns'])
            || empty($definition['defaultSort'])
            || !is_array($definition['defaultSort'])) {
            throw new RuntimeException("Invalid SQL resource registry entry: {$resource}");
        }
        foreach ($definition['columns'] as $column) {
            if (!$this->isIdentifier($column)) {
                throw new RuntimeException("Invalid SQL resource column: {$resource}");
            }
        }
        [$filterColumns, $filterValueTypes, $filterPlacement, $filters] =
            array_key_exists('filters', $definition)
                ? $this->resolveMappedFilters($definition, $resource)
                : $this->resolveLegacyFilters($definition, $resource);
        foreach ($definition['defaultSort'] as $sort) {
            if (!is_array($sort)
                || !$this->containsCaseInsensitive($definition['columns'], $sort['field'] ?? null)
                || !in_array(strtoupper((string)($sort['direction'] ?? '')), ['ASC', 'DESC'], true)) {
                throw new RuntimeException("Invalid SQL resource default sort: {$resource}");
            }
        }

        $realFile = is_string($definition['file']) ? realpath($definition['file']) : false;
        if ($realFile === false || !$this->isInsideRoot($realFile) || !is_file($realFile)
            || strtolower(pathinfo($realFile, PATHINFO_EXTENSION)) !== 'sql') {
            throw new RuntimeException("SQL resource file is unavailable: {$resource}");
        }
        $sql = file_get_contents($realFile);
        $markerCount = $sql === false ? 0 : substr_count($sql, self::RUNTIME_FILTER_MARKER);
        if (($filterPlacement === 'source' && $markerCount !== 1)
            || (in_array($filterPlacement, ['output', 'mapped'], true) && $markerCount !== 0)) {
            throw new RuntimeException("Invalid SQL resource runtime filter marker: {$resource}");
        }

        return [
            'resource' => $resource,
            'file' => $realFile,
            'columns' => array_values($definition['columns']),
            'filterColumns' => array_values($filterColumns),
            'filterValueTypes' => $filterValueTypes,
            'filterPlacement' => $filterPlacement,
            'filters' => $filters,
            'defaultSort' => array_values($definition['defaultSort']),
            'discovered' => false,
        ];
    }

    private function applyExecutionMetadata(array $definition, array $execution): array
    {
        if (array_diff(array_keys($execution), ['columns', 'filters', 'defaultSort']) !== []) {
            throw new RuntimeException('Invalid SQL execution metadata.');
        }
        $columns = $execution['columns'] ?? $definition['columns'];
        if (!is_array($columns)
            || (array_key_exists('columns', $execution) && !$this->isIdentifierList($columns))
            || ($columns !== [] && !$this->isIdentifierList($columns))) {
            throw new RuntimeException('Invalid SQL execution columns.');
        }
        $defaultSort = $execution['defaultSort'] ?? $definition['defaultSort'];
        if (!is_array($defaultSort)
            || (array_key_exists('defaultSort', $execution)
                && ($defaultSort === [] || !array_is_list($defaultSort)))) {
            throw new RuntimeException('Invalid SQL execution default sort.');
        }
        foreach ($defaultSort as $sort) {
            if (!is_array($sort)
                || !$this->containsCaseInsensitive($columns, $sort['field'] ?? null)
                || !in_array(strtoupper((string)($sort['direction'] ?? '')), ['ASC', 'DESC'], true)) {
                throw new RuntimeException('Invalid SQL execution default sort.');
            }
        }

        $filters = [];
        foreach ($columns as $column) {
            $filters[strtolower($column)] = [
                'field' => $column,
                'expression' => $column,
                'location' => 'output',
                'valueType' => null,
                'mappedExpression' => false,
            ];
        }
        $executionFilters = $execution['filters'] ?? [];
        if (array_key_exists('filters', $execution)
            && (!is_array($executionFilters) || $executionFilters === [] || array_is_list($executionFilters))) {
            throw new RuntimeException('Invalid SQL execution filter metadata.');
        }
        $seenFilters = [];
        foreach ($executionFilters as $field => $mapping) {
            if (!$this->isIdentifier($field) || !is_array($mapping)
                || ($mapping !== [] && array_is_list($mapping))
                || array_diff(array_keys($mapping), ['expression', 'valueType', 'placement']) !== []) {
                throw new RuntimeException('Invalid SQL execution filter metadata.');
            }
            $canonicalField = strtolower($field);
            if (isset($seenFilters[$canonicalField])) {
                throw new RuntimeException('Invalid SQL execution filter metadata.');
            }
            $seenFilters[$canonicalField] = true;
            $placement = $mapping['placement'] ?? 'output';
            $expression = $mapping['expression'] ?? $field;
            $valueType = $mapping['valueType'] ?? null;
            $location = $placement === 'source' ? 'where' : $placement;
            $validExpression = $placement === 'output'
                ? $this->isIdentifier($expression) && $this->containsCaseInsensitive($columns, $expression)
                : ($placement === 'source'
                    ? is_string($expression) && $this->isQualifiedIdentifier($expression)
                    : is_string($expression) && $this->isSafeAggregateExpression($expression));
            if (!in_array($placement, ['output', 'source', 'having'], true)
                || !$validExpression
                || ($valueType !== null && $valueType !== 'integer-date')) {
                throw new RuntimeException('Invalid SQL execution filter metadata.');
            }
            $filters[$canonicalField] = [
                'field' => $field,
                'expression' => trim($expression),
                'location' => $location,
                'valueType' => $valueType,
                'mappedExpression' => $placement !== 'output',
            ];
        }

        $definition['columns'] = array_values($columns);
        $definition['filterColumns'] = array_values(array_map(
            fn (array $filter): string => $filter['field'],
            $filters
        ));
        $definition['filterValueTypes'] = array_filter(
            array_map(fn (array $filter) => $filter['valueType'], $filters),
            fn ($value) => $value !== null
        );
        $definition['filterPlacement'] = 'mapped';
        $definition['filters'] = $filters;
        $definition['defaultSort'] = array_values($defaultSort);
        return $definition;
    }

    private function resolveLegacyFilters(array $definition, string $resource): array
    {
        $filterColumns = $definition['filterColumns'] ?? $definition['columns'];
        if (!$this->isIdentifierList($filterColumns)) {
            throw new RuntimeException("Invalid SQL resource filter columns: {$resource}");
        }
        $filterValueTypes = [];
        foreach ($definition['filterValueTypes'] ?? [] as $column => $type) {
            $canonicalColumn = $this->canonicalValue($filterColumns, (string)$column);
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
            if (!$this->isIdentifier($field) || !is_array($mapping)
                || array_diff(array_keys($mapping), ['expression', 'location', 'valueType']) !== []) {
                throw new RuntimeException("Invalid SQL resource filter mapping: {$resource}");
            }
            $expression = $mapping['expression'] ?? null;
            $location = $mapping['location'] ?? null;
            $valueType = $mapping['valueType'] ?? null;
            if (!is_string($expression) || trim($expression) === ''
                || preg_match('/[?;\x00]|--|\/\*|\*\//', $expression) === 1
                || !in_array($location, ['output', 'where', 'having'], true)
                || ($location === 'output'
                    && (!$this->isIdentifier($expression)
                        || !$this->containsCaseInsensitive($definition['columns'], $expression)))
                || ($valueType !== null && $valueType !== 'integer-date')) {
                throw new RuntimeException("Invalid SQL resource filter mapping: {$resource}");
            }
            $canonical = strtolower($field);
            if (isset($filters[$canonical])) {
                throw new RuntimeException("Duplicate SQL resource filter mapping: {$resource}");
            }
            $filterColumns[] = $field;
            if ($valueType !== null) $filterValueTypes[$canonical] = $valueType;
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

    private function isResourceIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*(?:\/[A-Za-z0-9][A-Za-z0-9_-]*)*$/', $value) === 1;
    }

    private function isPathSegment($value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $value) === 1;
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

    private function containsCaseInsensitive(array $values, $expected): bool
    {
        if (!is_string($expected)) return false;
        return $this->canonicalValue($values, $expected) !== null;
    }

    private function canonicalValue(array $values, string $expected): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && strcasecmp($value, $expected) === 0) return $value;
        }
        return null;
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

    private function isExcludedPath(array $segments): bool
    {
        foreach ($segments as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')
                || in_array(strtolower($segment), $this->excludedDirectories, true)) return true;
        }
        return false;
    }

    private function isInsideRoot(string $path): bool
    {
        return $path !== $this->resourceRoot
            && str_starts_with($path, $this->resourceRoot . DIRECTORY_SEPARATOR);
    }

    private function invalidResource(): never
    {
        throw new ApiRequestException(
            'Invalid SQL resource.',
            'INVALID_SQL_RESOURCE',
            [['path' => 'resource', 'message' => 'Resource is not available.']]
        );
    }
}
