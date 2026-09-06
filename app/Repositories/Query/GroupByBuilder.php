<?php

class GroupByBuilder
{
    private MetadataRepository $metadataRepository;
    private $columnResolver;

    public function __construct(MetadataRepository $metadataRepository, callable $columnResolver)
    {
        $this->metadataRepository = $metadataRepository;
        $this->columnResolver = $columnResolver;
    }

    public function build(array $request): string
    {
        if (empty($request['groupBy'])) {
            return '';
        }
        foreach ($request['groupBy'] as $column) {
            $resolved = ($this->columnResolver)($column);
            $table = $resolved['table'] ?? $request['table'];
            if (!$this->metadataRepository->columnExists($table, $resolved['column'])) {
                throw new Exception("Invalid GROUP BY column: {$column}");
            }
        }
        return ' GROUP BY ' . implode(', ', $request['groupBy']);
    }
}
