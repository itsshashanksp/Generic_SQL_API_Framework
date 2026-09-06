<?php

class WhereBuilder
{
    private MetadataRepository $metadataRepository;
    private $columnResolver;
    private $selectBuilder;

    public function __construct(
        MetadataRepository $metadataRepository,
        callable $columnResolver,
        callable $selectBuilder
    ) {
        $this->metadataRepository = $metadataRepository;
        $this->columnResolver = $columnResolver;
        $this->selectBuilder = $selectBuilder;
    }

    public function build(array $request, array $params): array
    {
        if (empty($request['where'])) {
            return ['sql' => '', 'params' => $params];
        }

        foreach ($request['where'] as $filter) {
            $operator = strtoupper($filter['operator']);
            if ($operator === 'EXISTS' || $operator === 'NOT EXISTS') {
                continue;
            }
            $resolved = ($this->columnResolver)($filter['column']);
            $table = $resolved['table'] ?? $request['table'];
            if (!$this->metadataRepository->columnExists($table, $resolved['column'])) {
                throw new Exception("Invalid filter column: {$filter['column']}");
            }
        }

        $conditions = [];
        $allowed = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE',
            'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'IS NULL', 'IS NOT NULL',
            'EXISTS', 'NOT EXISTS'];

        foreach ($request['where'] as $filter) {
            $operator = strtoupper($filter['operator']);
            if (!in_array($operator, $allowed)) {
                throw new Exception("Invalid operator: {$filter['operator']}");
            }
            if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                $conditions[] = "{$filter['column']} {$filter['operator']}";
                continue;
            }
            if ($operator === 'EXISTS' || $operator === 'NOT EXISTS') {
                if (!isset($filter['subquery'])) {
                    throw new Exception("{$filter['operator']} requires a subquery.");
                }
                $subQuery = ($this->selectBuilder)($filter['subquery'], true);
                $conditions[] = "{$filter['operator']} ({$subQuery['sql']})";
                $params = array_merge($params, $subQuery['params']);
                continue;
            }
            if ($operator === 'IN' || $operator === 'NOT IN') {
                if (isset($filter['subquery'])) {
                    $subQuery = ($this->selectBuilder)($filter['subquery'], true);
                    $conditions[] = "{$filter['column']} {$filter['operator']} ({$subQuery['sql']})";
                    $params = array_merge($params, $subQuery['params']);
                    continue;
                }
                if (!is_array($filter['value']) || empty($filter['value'])) {
                    throw new Exception("{$filter['operator']} requires at least one value.");
                }
                $placeholders = implode(', ', array_fill(0, count($filter['value']), '?'));
                $conditions[] = "{$filter['column']} {$filter['operator']} ({$placeholders})";
                foreach ($filter['value'] as $value) {
                    $params[] = $value;
                }
                continue;
            }
            if ($operator === 'BETWEEN' || $operator === 'NOT BETWEEN') {
                if (!is_array($filter['value']) || count($filter['value']) != 2) {
                    throw new Exception("{$filter['operator']} requires exactly two values.");
                }
                $resolved = ($this->columnResolver)($filter['column']);
                $table = $resolved['table'] ?? $request['table'];
                $dataType = $this->metadataRepository->getColumnDataType($table, $resolved['column']);
                if (in_array(strtolower((string)$dataType), ['int', 'bigint', 'smallint', 'tinyint'], true)) {
                    foreach ([0, 1] as $index) {
                        $value = $filter['value'][$index];
                        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                            $filter['value'][$index] = (int)str_replace('-', '', $value);
                        }
                    }
                }
                $conditions[] = "{$filter['column']} {$filter['operator']} ? AND ?";
                $params[] = $filter['value'][0];
                $params[] = $filter['value'][1];
                continue;
            }
            $conditions[] = "{$filter['column']} {$filter['operator']} ?";
            $params[] = $filter['value'];
        }

        $conditionType = 'AND';
        if (!empty($request['condition'])) {
            $conditionType = strtoupper($request['condition']);
            if (!in_array($conditionType, ['AND', 'OR'])) {
                throw new Exception('Condition must be AND or OR.');
            }
        }
        return ['sql' => ' WHERE ' . implode(" {$conditionType} ", $conditions), 'params' => $params];
    }
}
