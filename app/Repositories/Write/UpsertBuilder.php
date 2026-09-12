<?php

require_once __DIR__ . '/WriteSqlBuilder.php';

class UpsertBuilder extends WriteSqlBuilder
{
    public function build(array $resource, array $data, array $keys): array
    {
        $columns = array_keys($data);
        $nonKeys = array_values(array_filter(
            $columns,
            fn ($column) => !in_array(strtolower($column), array_map('strtolower', $keys), true)
        ));
        $sourceColumns = implode(', ', array_map(fn ($column) => $this->quote($column), $columns));
        $matches = implode(' AND ', array_map(
            fn ($key) => '[target].' . $this->quote($key) . ' = [source].' . $this->quote($key),
            $keys
        ));
        $sql = 'MERGE INTO ' . $this->target($resource) . ' WITH (HOLDLOCK) AS [target] '
            . 'USING (VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')) '
            . 'AS [source] (' . $sourceColumns . ') ON ' . $matches . ' ';
        if ($nonKeys !== []) {
            $sql .= 'WHEN MATCHED THEN UPDATE SET ' . implode(', ', array_map(
                fn ($column) => '[target].' . $this->quote($column)
                    . ' = [source].' . $this->quote($column),
                $nonKeys
            )) . ' ';
        }
        $sql .= 'WHEN NOT MATCHED THEN INSERT (' . $sourceColumns . ') VALUES ('
            . implode(', ', array_map(
                fn ($column) => '[source].' . $this->quote($column),
                $columns
            )) . ') ' . $this->outputClause($resource, true, true);
        return ['sql' => $this->batch($sql), 'params' => array_values($data)];
    }
}
