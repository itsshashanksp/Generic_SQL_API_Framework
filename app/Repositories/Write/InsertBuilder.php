<?php

require_once __DIR__ . '/WriteSqlBuilder.php';

class InsertBuilder extends WriteSqlBuilder
{
    public function build(array $resource, array $data): array
    {
        $columns = array_keys($data);
        $statement = 'INSERT INTO ' . $this->target($resource)
            . ' (' . implode(', ', array_map(fn ($column) => $this->quote($column), $columns)) . ') '
            . $this->outputClause($resource, false, true)
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        return ['sql' => $this->batch($statement), 'params' => array_values($data)];
    }
}
