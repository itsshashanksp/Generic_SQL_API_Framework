<?php

class HavingBuilder
{
    private MetadataRepository $metadataRepository;
    private $columnResolver;

    public function __construct(MetadataRepository $metadataRepository, callable $columnResolver)
    {
        $this->metadataRepository = $metadataRepository;
        $this->columnResolver = $columnResolver;
    }

    public function build(array $request, array $params): array
    {
        if (empty($request['having'])) {
            return ['sql' => '', 'params' => $params];
        }
        $conditions = [];
        foreach ($request['having'] as $having) {
            if (!in_array(strtoupper($having['function']), ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'STRING_AGG'])) {
                throw new Exception("Invalid HAVING function: {$having['function']}");
            }
            if (strtoupper($having['column']) != '*') {
                $resolved = ($this->columnResolver)($having['column']);
                $table = $resolved['table'] ?? $request['table'];
                if (!$this->metadataRepository->columnExists($table, $resolved['column'])) {
                    throw new Exception("Invalid HAVING column: {$having['column']}");
                }
            }
            if (!in_array(strtoupper($having['operator']), ['=', '!=', '<>', '>', '<', '>=', '<='])) {
                throw new Exception("Invalid HAVING operator: {$having['operator']}");
            }
            $conditions[] = strtoupper($having['function']) . '(' . $having['column'] . ') '
                . $having['operator'] . ' ?';
            $params[] = $having['value'];
        }
        return ['sql' => ' HAVING ' . implode(' AND ', $conditions), 'params' => $params];
    }
}
