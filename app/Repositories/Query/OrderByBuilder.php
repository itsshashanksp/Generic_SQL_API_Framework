<?php

class OrderByBuilder
{
    private MetadataRepository $metadataRepository;
    private $columnResolver;

    public function __construct(MetadataRepository $metadataRepository, callable $columnResolver)
    {
        $this->metadataRepository = $metadataRepository;
        $this->columnResolver = $columnResolver;
    }

    public function buildItems(
        array $orders,
        array $request,
        bool $allowSelectedAliases = false,
        bool $useOutputNames = false,
        bool $windowContext = false
    ): array {
        $sqlOrders = [];

        foreach ($orders as $order) {
            if (!is_array($order) || empty($order['column']) || !is_string($order['column'])) {
                throw new Exception('ORDER BY column is required.');
            }

            $orderColumn = $order['column'];
            $isAlias = false;
            $isPosition = false;

            if (ctype_digit($orderColumn)) {
                $position = (int)$orderColumn;
                if ($position < 1) {
                    throw new Exception('ORDER BY position must be greater than zero.');
                }

                if ($windowContext) {
                    $resolvedPosition = $this->resolveWindowPosition(
                        $position,
                        $request,
                        $useOutputNames
                    );
                    $orderColumn = $resolvedPosition['column'];
                    $isAlias = $resolvedPosition['isAlias'];
                } else {
                    $this->validateOrderPosition($position, $request);
                    $isPosition = true;
                }
            }

            if ($allowSelectedAliases) {
                foreach ($request['columns'] as $selectedColumn) {
                    if (is_array($selectedColumn)
                        && !empty($selectedColumn['alias'])
                        && $selectedColumn['alias'] === $orderColumn) {
                        $isAlias = true;
                        break;
                    }
                }
            }

            if ($isAlias) {
                $columnName = '[' . $orderColumn . ']';
            } elseif ($isPosition) {
                // Positional ordering is valid in a top-level SELECT only.
                $columnName = $orderColumn;
            } else {
                $resolved = ($this->columnResolver)($orderColumn);
                $table = $resolved['table'] ?? $request['table'];
                if (!$this->metadataRepository->columnExists($table, $resolved['column'])) {
                    throw new Exception("Invalid ORDER BY column: {$order['column']}");
                }
                $columnName = $useOutputNames
                    ? '[' . $resolved['column'] . ']'
                    : $orderColumn;
            }

            $direction = strtoupper($order['direction'] ?? 'ASC');
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new Exception("Invalid sort direction: {$direction}");
            }
            $sqlOrders[] = $columnName . ' ' . $direction;
        }

        return $sqlOrders;
    }

    private function validateOrderPosition(int $position, array $request): void
    {
        $selectedColumn = $request['columns'][$position - 1] ?? null;
        if ($selectedColumn === null) {
            throw new Exception("Invalid ORDER BY position: {$position}");
        }

        if ($selectedColumn === '*') {
            $metadata = $this->metadataRepository->getColumns($request['table']);
            if (empty($metadata['data'][$position - 1]['COLUMN_NAME'])) {
                throw new Exception("Invalid ORDER BY position: {$position}");
            }
        }
    }

    /**
     * SQL Server does not allow SELECT-list positions (ORDER BY 1) inside a
     * window. Resolve the position to the corresponding validated projection.
     */
    private function resolveWindowPosition(
        int $position,
        array $request,
        bool $useOutputNames
    ): array {
        $selectedColumn = $request['columns'][$position - 1] ?? null;

        if ($selectedColumn === '*') {
            $metadata = $this->metadataRepository->getColumns($request['table']);
            $columnName = $metadata['data'][$position - 1]['COLUMN_NAME'] ?? null;
            if (empty($columnName)) {
                throw new Exception("Invalid window ORDER BY position: {$position}");
            }
            return ['column' => $columnName, 'isAlias' => false];
        }

        if (is_string($selectedColumn) && $selectedColumn !== '') {
            return ['column' => $selectedColumn, 'isAlias' => false];
        }

        if (is_array($selectedColumn)) {
            if ($useOutputNames) {
                $alias = $selectedColumn['alias']
                    ?? ($selectedColumn['case']['alias'] ?? null);
                if (!empty($alias)) {
                    return ['column' => $alias, 'isAlias' => true];
                }
            }

            if (!empty($selectedColumn['column'])) {
                return ['column' => $selectedColumn['column'], 'isAlias' => false];
            }
        }

        throw new Exception(
            "Window ORDER BY position {$position} does not resolve to a validated column."
        );
    }

    public function getDefaultColumn(array $request): string
    {
        foreach ($request['columns'] as $column) {
            if (is_string($column)) {
                if ($column === '*') {
                    continue;
                }
                $resolved = ($this->columnResolver)($column);
                return '[' . $resolved['column'] . ']';
            }
            if (!empty($column['alias'])) {
                return '[' . $column['alias'] . ']';
            }
            if (isset($column['case'])) {
                return '[' . ($column['case']['alias'] ?? 'CaseValue') . ']';
            }
            if (isset($column['expression'])) {
                return '[Expression]';
            }
            if (isset($column['function'])) {
                return '[' . strtolower($column['function']) . ']';
            }
            if (!empty($column['column'])) {
                $resolved = ($this->columnResolver)($column['column']);
                return '[' . $resolved['column'] . ']';
            }
        }

        $metadata = $this->metadataRepository->getColumns($request['table']);
        $firstColumn = $metadata['data'][0]['COLUMN_NAME'] ?? null;
        if (empty($firstColumn)) {
            throw new Exception('Unable to determine a column for pagination ordering.');
        }
        return '[' . $firstColumn . ']';
    }
}
