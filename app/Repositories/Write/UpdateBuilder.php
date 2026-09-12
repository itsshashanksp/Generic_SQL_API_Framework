<?php

require_once __DIR__ . '/WriteSqlBuilder.php';
require_once __DIR__ . '/WriteFilterBuilder.php';

class UpdateBuilder extends WriteSqlBuilder
{
    private WriteFilterBuilder $filterBuilder;

    public function __construct(?WriteFilterBuilder $filterBuilder = null)
    {
        $this->filterBuilder = $filterBuilder ?? new WriteFilterBuilder();
    }

    public function build(array $resource, array $data, array $filters, string $logic): array
    {
        $where = $this->filterBuilder->build($filters, $logic);
        $assignments = array_map(
            fn ($column) => $this->quote($column) . ' = ?',
            array_keys($data)
        );
        return [
            'sql' => $this->batch('UPDATE ' . $this->target($resource)
                . ' SET ' . implode(', ', $assignments) . ' '
                . $this->outputClause($resource)
                . ' WHERE ' . $where['sql']),
            'params' => array_merge(array_values($data), $where['params']),
        ];
    }
}
