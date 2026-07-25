<?php

require_once __DIR__ . '/QueryRepository.php';

class UnionBuilder
{
    private QueryRepository $queryRepository;
    private QueryEngine $queryEngine;

public function __construct(QueryRepository $queryRepository)
{
    $this->queryRepository = $queryRepository;
    $this->queryEngine = new QueryEngine();
}

    public function build(array $request)
    {
        
        if (empty($request['queries'])) {
            throw new Exception("At least one query is required.");
        }

        $unionType = strtoupper($request['type'] ?? 'UNION');

        if (!in_array($unionType, ['UNION', 'UNION ALL'])) {
            throw new Exception("Invalid UNION type.");
        }

        $sqlParts = [];
        $params = [];

        foreach ($request['queries'] as $query) {

            $result = $this->queryRepository->buildSelect($query, true);

            $sqlParts[] = trim($result['sql']);

            $params = array_merge(
                $params,
                $result['params']
            );
        }

        $sql = implode(
            " {$unionType} ",
            $sqlParts
        );

        return $this->queryEngine->executePrepared(
            $sql,
            $params
        );
    }
}