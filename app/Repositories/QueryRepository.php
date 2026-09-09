<?php

require_once __DIR__ . '/../../core/QueryEngine.php';
require_once __DIR__ . '/MetadataRepository.php';
require_once __DIR__ . '/Query/SelectBuilder.php';
require_once __DIR__ . '/Query/RoutineBuilder.php';
require_once __DIR__ . '/SetOperationBuilder.php';

/** Public compatibility facade for query construction and execution. */
class QueryRepository
{
    private QueryEngine $queryEngine;
    private SelectBuilder $selectBuilder;
    private RoutineBuilder $routineBuilder;
    private SetOperationBuilder $setOperationBuilder;
    private Logger $logger;

    public function __construct(?QueryEngine $queryEngine = null, ?MetadataRepository $metadataRepository = null, ?Logger $logger = null)
    {
        $this->logger = $logger ?? new Logger();
        $this->queryEngine = $queryEngine ?? new QueryEngine(null, $this->logger);
        $metadataRepository = $metadataRepository ?? new MetadataRepository($this->queryEngine);
        $this->selectBuilder = new SelectBuilder($this->queryEngine, $metadataRepository, $this->logger);
        $this->routineBuilder = new RoutineBuilder();
        $this->setOperationBuilder = new SetOperationBuilder($this, $this->queryEngine);
    }

    public function select($request)
    {
        if (isset($request['queries'])) {
            return $this->setOperationBuilder->build($request);
        }
        $query = $this->buildSelect($request);
        $result = $this->queryEngine->executePrepared($query['sql'], $query['params'], [
            'action' => 'select',
            'queryPhase' => 'data',
            'page' => $request['page'] ?? null,
            'pageSize' => $request['pageSize'] ?? null,
        ]);
        if ($query['totalRows'] !== null) {
            $result['totalRows'] = $query['totalRows'];
        }
        return $result;
    }

    public function procedure(array $request)
    {
        $query = $this->buildProcedure($request);
        return $this->queryEngine->executePreparedQuery($query['sql'], $query['params'], [
            'action' => 'procedure', 'queryPhase' => 'data'
        ]);
    }

    public function function(array $request)
    {
        $query = $this->buildFunction($request);
        return $this->queryEngine->executePreparedQuery($query['sql'], $query['params'], [
            'action' => 'function', 'queryPhase' => 'data'
        ]);
    }

    public function tableFunction(array $request)
    {
        $query = $this->buildTableFunction($request);
        return $this->queryEngine->executePreparedQuery($query['sql'], $query['params'], [
            'action' => 'tableFunction', 'queryPhase' => 'data'
        ]);
    }

    public function buildSelect($request, bool $isUnion = false)
    {
        return $this->selectBuilder->build($request, $isUnion);
    }

    public function buildProcedure(array $request)
    {
        return $this->routineBuilder->buildProcedure($request);
    }

    public function buildFunction(array $request)
    {
        return $this->routineBuilder->buildFunction($request);
    }

    public function buildTableFunction(array $request)
    {
        return $this->routineBuilder->buildTableFunction($request);
    }
}
