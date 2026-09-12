<?php

require_once __DIR__ . '/../../core/QueryEngine.php';
require_once __DIR__ . '/MetadataRepository.php';
require_once __DIR__ . '/../Resources/WriteResourceRegistry.php';
require_once __DIR__ . '/../Requests/WritePayloadValidator.php';
require_once __DIR__ . '/Write/InsertBuilder.php';
require_once __DIR__ . '/Write/UpdateBuilder.php';
require_once __DIR__ . '/Write/DeleteBuilder.php';
require_once __DIR__ . '/Write/UpsertBuilder.php';

class WriteRepository
{
    private QueryEngine $queryEngine;
    private MetadataRepository $metadataRepository;
    private WriteResourceRegistry $registry;
    private WritePayloadValidator $payloadValidator;
    private InsertBuilder $insertBuilder;
    private UpdateBuilder $updateBuilder;
    private DeleteBuilder $deleteBuilder;
    private UpsertBuilder $upsertBuilder;

    public function __construct(
        ?QueryEngine $queryEngine = null,
        ?MetadataRepository $metadataRepository = null,
        ?WriteResourceRegistry $registry = null,
        ?WritePayloadValidator $payloadValidator = null
    ) {
        $this->queryEngine = $queryEngine ?? new QueryEngine();
        $this->metadataRepository = $metadataRepository ?? new MetadataRepository($this->queryEngine);
        $this->registry = $registry ?? new WriteResourceRegistry();
        $this->payloadValidator = $payloadValidator ?? new WritePayloadValidator();
        $this->insertBuilder = new InsertBuilder();
        $this->updateBuilder = new UpdateBuilder();
        $this->deleteBuilder = new DeleteBuilder();
        $this->upsertBuilder = new UpsertBuilder();
    }

    public function execute(array $request): array
    {
        $resource = $this->registry->resolve($request['resource'], $request['action']);
        $metadataResult = $this->metadataRepository->getWriteColumns(
            $resource['schema'],
            $resource['table']
        );
        $request = $this->payloadValidator->validate(
            $request,
            $resource,
            $metadataResult['data'] ?? []
        );
        if ($request['action'] === 'upsert'
            && !$this->metadataRepository->hasUniqueKey(
                $resource['schema'],
                $resource['table'],
                $request['keys']
            )) {
            throw new RuntimeException(
                "Configured UPSERT key is not protected by a unique index: {$resource['resource']}"
            );
        }
        $query = $this->build($request, $resource);

        try {
            $result = $this->queryEngine->executePreparedQuery(
                $query['sql'],
                $query['params'],
                ['action' => $request['action'], 'queryPhase' => 'write', 'resource' => $request['resource']]
            );
        } catch (Throwable $exception) {
            $this->throwClassifiedDatabaseError($exception);
        }

        return $this->formatResult($request['action'], $result);
    }

    public function build(array $request, array $resource): array
    {
        return match ($request['action']) {
            'insert' => $this->insertBuilder->build($resource, $request['data']),
            'update' => $this->updateBuilder->build(
                $resource,
                $request['data'],
                $request['filters'],
                $request['filterLogic'] ?? 'AND'
            ),
            'delete' => $this->deleteBuilder->build(
                $resource,
                $request['filters'],
                $request['filterLogic'] ?? 'AND'
            ),
            'upsert' => $this->upsertBuilder->build($resource, $request['data'], $request['keys']),
            default => throw new LogicException('Unsupported write action.'),
        };
    }

    private function formatResult(string $requestedAction, array $result): array
    {
        $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
        $outputRows = array_values(array_filter(
            $rows,
            fn ($row) => is_array($row) && $this->rowValue($row, '__affected') !== null
        ));
        $affectedRows = count($outputRows);
        $operation = $requestedAction;
        if ($requestedAction === 'upsert' && isset($outputRows[0])) {
            $databaseOperation = strtolower((string)$this->rowValue($outputRows[0], '__operation'));
            if (in_array($databaseOperation, ['insert', 'update'], true)) {
                $operation = $databaseOperation;
            }
        }
        $payload = ['operation' => $operation, 'affectedRows' => $affectedRows];
        if (isset($outputRows[0]) && ($requestedAction === 'insert'
            || ($requestedAction === 'upsert' && $operation === 'insert'))) {
            $generatedId = $this->rowValue($outputRows[0], '__generatedId');
            if ($generatedId !== null) $payload['generatedId'] = $generatedId;
        }

        return [
            'executionTime' => $result['executionTime'] ?? null,
            'rowsReturned' => 0,
            'totalRows' => 0,
            'affectedRows' => $affectedRows,
            'data' => [$payload],
        ];
    }

    private function rowValue(array $row, string $key)
    {
        foreach ($row as $name => $value) {
            if (strcasecmp((string)$name, $key) === 0) return $value;
        }
        return null;
    }

    private function throwClassifiedDatabaseError(Throwable $exception): never
    {
        $message = $exception->getMessage();
        if (preg_match('/(?:\b2601\b|\b2627\b|duplicate key|unique (?:index|constraint))/i', $message) === 1) {
            throw new ApiRequestException(
                'Duplicate key conflict.',
                'DUPLICATE_KEY',
                [],
                409
            );
        }
        if (preg_match('/(?:\b23000\b|\b547\b|\b515\b|constraint|cannot insert the value null|truncated)/i', $message) === 1) {
            throw new ApiRequestException(
                'Database constraint violation.',
                'CONSTRAINT_VIOLATION',
                [],
                409
            );
        }
        throw $exception;
    }
}
