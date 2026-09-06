<?php

class RoutineBuilder
{
    public function buildProcedure(array $request): array
    {
        if (empty($request['procedure'])) {
            throw new Exception('Procedure name is required.');
        }
        $params = $request['params'] ?? [];
        $placeholders = $this->buildPlaceholders($params);
        $sql = "EXEC {$request['procedure']}";
        if ($placeholders !== '') {
            $sql .= ' ' . $placeholders;
        }
        return ['sql' => $sql, 'params' => $params];
    }

    public function buildFunction(array $request): array
    {
        if (empty($request['function'])) {
            throw new Exception('Function name is required.');
        }
        $params = $request['params'] ?? [];
        $placeholders = $this->buildPlaceholders($params);
        $sql = "SELECT {$request['function']}(";
        if ($placeholders !== '') {
            $sql .= $placeholders;
        }
        $sql .= ') AS Result';
        return ['sql' => $sql, 'params' => $params];
    }

    public function buildTableFunction(array $request): array
    {
        if (empty($request['function'])) {
            throw new Exception('Function name is required.');
        }
        $params = $request['params'] ?? [];
        $placeholders = $this->buildPlaceholders($params);
        $sql = "SELECT * FROM {$request['function']}(";
        if ($placeholders !== '') {
            $sql .= $placeholders;
        }
        $sql .= ')';
        return ['sql' => $sql, 'params' => $params];
    }

    private function buildPlaceholders(array $params): string
    {
        return count($params) > 0
            ? implode(', ', array_fill(0, count($params), '?'))
            : '';
    }
}
