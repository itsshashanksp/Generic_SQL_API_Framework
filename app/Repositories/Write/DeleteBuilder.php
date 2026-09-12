<?php

require_once __DIR__ . '/WriteSqlBuilder.php';
require_once __DIR__ . '/WriteFilterBuilder.php';

class DeleteBuilder extends WriteSqlBuilder
{
    private WriteFilterBuilder $filterBuilder;

    public function __construct(?WriteFilterBuilder $filterBuilder = null)
    {
        $this->filterBuilder = $filterBuilder ?? new WriteFilterBuilder();
    }

    public function build(array $resource, array $filters, string $logic): array
    {
        $where = $this->filterBuilder->build($filters, $logic);
        return [
            'sql' => $this->batch('DELETE FROM ' . $this->target($resource) . ' '
                . $this->outputClause($resource)
                . ' WHERE ' . $where['sql']),
            'params' => $where['params'],
        ];
    }
}
