<?php

class JoinBuilder
{
    private MetadataRepository $metadataRepository;

    public function __construct(MetadataRepository $metadataRepository)
    {
        $this->metadataRepository = $metadataRepository;
    }

    public function build(array $request): string
    {
        $sql = '';
        if (empty($request['joins'])) {
            return $sql;
        }
        foreach ($request['joins'] as $join) {
            $type = strtoupper($join['type']);
            if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'])) {
                throw new Exception("Invalid JOIN type: {$type}");
            }
            if (!$this->metadataRepository->tableExists($join['table'])) {
                throw new Exception("Invalid JOIN table: {$join['table']}");
            }
            $alias = !empty($join['alias']) ? ' ' . $join['alias'] : '';
            $sql .= "
            {$type} JOIN {$join['table']}{$alias}
            ON {$join['left']} = {$join['right']}
        ";
        }
        return $sql;
    }
}
