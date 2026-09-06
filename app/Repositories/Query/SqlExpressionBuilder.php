<?php

class SqlExpressionBuilder
{
    private array $tableMap = [];

    public function resetTables(): void
    {
        $this->tableMap = [];
    }

    public function registerTable(string $name, string $table): void
    {
        $this->tableMap[$name] = $table;
    }

    public function resolveColumn(string $column): array
    {
        if (strpos($column, '.') === false) {
            return ['table' => null, 'column' => $column];
        }
        [$alias, $columnName] = explode('.', $column, 2);
        if (!isset($this->tableMap[$alias])) {
            throw new Exception("Unknown table alias: {$alias}");
        }
        return ['table' => $this->tableMap[$alias], 'column' => $columnName];
    }

    public function buildValue($value): string
    {
        if (is_numeric($value)) {
            return (string)$value;
        }
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return $this->buildExpression($value);
        }
        return "'" . str_replace("'", "''", $value) . "'";
    }

    public function buildExpression($expression): string
    {
        if (is_numeric($expression) || is_string($expression)
            || is_bool($expression) || $expression === null) {
            return $this->buildValue($expression);
        }
        if (isset($expression['column'])) {
            $resolved = $this->resolveColumn($expression['column']);
            return !empty($resolved['table'])
                ? $resolved['table'] . '.' . $resolved['column']
                : $resolved['column'];
        }
        if (isset($expression['expression'])) {
            return '(' . $this->buildExpression($expression['expression']['left'])
                . ' ' . $expression['expression']['operator'] . ' '
                . $this->buildExpression($expression['expression']['right']) . ')';
        }
        throw new Exception('Unsupported expression.');
    }

    public function buildCondition(array $condition): string
    {
        foreach (['left', 'operator', 'right'] as $field) {
            if (!array_key_exists($field, $condition)) {
                throw new Exception("Condition requires {$field}.");
            }
        }
        $allowed = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
        $operator = strtoupper($condition['operator']);
        if (!in_array($operator, $allowed)) {
            throw new Exception('Invalid condition operator.');
        }
        if ($operator === 'IN' || $operator === 'NOT IN') {
            if (!is_array($condition['right']) || empty($condition['right'])) {
                throw new Exception("{$operator} requires an array.");
            }
            $values = [];
            foreach ($condition['right'] as $value) {
                $values[] = $this->buildValue($value);
            }
            return $this->buildExpression($condition['left']) . " {$operator} ("
                . implode(', ', $values) . ')';
        }
        return $this->buildExpression($condition['left']) . " {$operator} "
            . $this->buildExpression($condition['right']);
    }
}
