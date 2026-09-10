<?php

class SetOperationBuilder
{
    private QueryRepository $queryRepository;
    private QueryEngine $queryEngine;

public function __construct(
    QueryRepository $queryRepository,
    ?QueryEngine $queryEngine = null
)
{
    $this->queryRepository = $queryRepository;
    $this->queryEngine = $queryEngine ?? new QueryEngine();
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
        $columnCount = null;

        foreach ($request['queries'] as $query) {

            $result = $this->queryRepository->buildSelect($query, true);

            $branchColumnCount = $result['columnCount'] ?? null;
            if ($branchColumnCount !== null) {
                if ($columnCount !== null && $branchColumnCount !== $columnCount) {
                    throw new Exception('Set-operation branches must return the same number of columns.');
                }
                $columnCount = $branchColumnCount;
            }

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
            $params,
            ['action' => 'union', 'queryPhase' => 'data']
        );
    }
}
