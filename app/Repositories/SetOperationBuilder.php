<?php

require_once __DIR__ . '/QueryRepository.php';

class SetOperationBuilder
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

        $setOperation = strtoupper($request['type'] ?? 'UNION');

        if (!in_array($setOperation, [
            'UNION', 
            'UNION ALL',
            'INTERSECT',
            'EXCEPT'
            ])) {
            throw new Exception("Invalid SET operation type.");
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
            " {$setOperation} ",
            $sqlParts
        );

        return $this->queryEngine->executePrepared(
            $sql,
            $params
        );
    }
}