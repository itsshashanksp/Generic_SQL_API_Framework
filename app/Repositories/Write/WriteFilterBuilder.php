<?php

class WriteFilterBuilder
{
    public function build(array $filters, string $logic = 'AND'): array
    {
        $conditions = [];
        $params = [];
        foreach ($filters as $filter) {
            $field = '[' . $filter['field'] . ']';
            $operator = $filter['operator'];
            if (in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
                $conditions[] = "{$field} {$operator}";
                continue;
            }
            if (in_array($operator, ['IN', 'NOT IN'], true)) {
                $conditions[] = "{$field} {$operator} ("
                    . implode(', ', array_fill(0, count($filter['value']), '?')) . ')';
                array_push($params, ...$filter['value']);
                continue;
            }
            if (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)) {
                $conditions[] = "{$field} {$operator} ? AND ?";
                array_push($params, ...$filter['value']);
                continue;
            }
            $conditions[] = "{$field} {$operator} ?";
            $params[] = $filter['value'];
        }
        return [
            'sql' => implode(' ' . $logic . ' ', array_map(fn ($condition) => "({$condition})", $conditions)),
            'params' => $params,
        ];
    }
}
