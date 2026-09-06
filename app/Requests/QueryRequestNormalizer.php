<?php

class QueryRequestNormalizer
{
    public function normalize(array $request): array
    {
        $action = $request['action'];
        if ($action === 'select') {
            return ['controller' => 'Query', 'action' => 'select'] + $this->normalizeSelect($request);
        }
        if ($action === 'union' || $action === 'unionAll') {
            return [
                'controller' => 'Query',
                'action' => 'union',
                'type' => $action === 'unionAll' ? 'UNION ALL' : 'UNION',
                'queries' => array_map(fn (array $query) => $this->normalizeSelect($query), $request['queries'])
            ];
        }
        if (in_array($action, ['procedure', 'function', 'tableFunction'], true)) {
            $key = $action === 'procedure' ? 'procedure' : 'function';
            return [
                'controller' => 'Query',
                'action' => $action,
                $key => $request['source'][$key],
                'params' => $request['parameters'] ?? []
            ];
        }

        $metadataAction = substr($action, strlen('metadata.'));
        $normalized = ['controller' => 'Metadata', 'action' => $metadataAction];
        if ($metadataAction === 'columns') {
            $normalized['table'] = $request['source']['table'];
        }
        return $normalized;
    }

    private function normalizeSelect(array $request): array
    {
        $normalized = [
            'table' => $request['source']['table'],
            'columns' => array_map(fn ($field) => $this->normalizeField($field), $request['fields'])
        ];
        if (!empty($request['source']['alias'])) { $normalized['alias'] = $request['source']['alias']; }
        if (array_key_exists('distinct', $request)) { $normalized['distinct'] = $request['distinct']; }
        if (isset($request['limit'])) { $normalized['top'] = $request['limit']; }
        if (isset($request['filterLogic'])) { $normalized['condition'] = $request['filterLogic']; }
        if (isset($request['filters'])) {
            $normalized['where'] = array_map(fn (array $filter) => $this->normalizeFilter($filter), $request['filters']);
        }
        if (isset($request['joins'])) {
            $normalized['joins'] = array_map(function (array $join): array {
                $item = [
                    'type' => strtoupper($join['type']),
                    'table' => $join['source']['table'],
                    'left' => $join['on']['left'],
                    'right' => $join['on']['right']
                ];
                if (!empty($join['source']['alias'])) { $item['alias'] = $join['source']['alias']; }
                return $item;
            }, $request['joins']);
        }
        if (isset($request['groupBy'])) { $normalized['groupBy'] = $request['groupBy']; }
        if (isset($request['having'])) {
            $normalized['having'] = array_map(fn (array $item) => [
                'function' => $item['function'], 'column' => $item['field'],
                'operator' => $item['operator'], 'value' => $item['value']
            ], $request['having']);
        }
        if (isset($request['sort'])) { $normalized['sort'] = $this->normalizeSort($request['sort']); }
        if (isset($request['pagination'])) {
            $normalized['page'] = $request['pagination']['page'];
            $normalized['pageSize'] = $request['pagination']['pageSize'];
        }
        if (isset($request['with'])) {
            $with = $request['with'];
            if (isset($with['anchor'], $with['recursive'])) {
                $normalized['recursiveCte'] = [
                    'name' => $with['name'],
                    'anchor' => $this->normalizeSelect($with['anchor']),
                    'recursive' => $this->normalizeSelect($with['recursive'])
                ];
            } else {
                $normalized['cte'] = [
                    'name' => $with['name'],
                    'query' => $this->normalizeSelect($with['query'])
                ];
            }
        }
        return $normalized;
    }

    private function normalizeField($field)
    {
        if (is_string($field)) { return $field; }
        $normalized = $this->normalizeExpressionKeys($field);
        if (isset($normalized['case']['when'])) {
            if (!empty($normalized['alias']) && empty($normalized['case']['alias'])) {
                $normalized['case']['alias'] = $normalized['alias'];
            }
        }
        return $normalized;
    }

    private function normalizeExpressionKeys(array $value): array
    {
        $normalized = [];
        foreach ($value as $key => $item) {
            if ($key === 'sort' && is_array($item)) {
                $normalized['orderBy'] = $this->normalizeSort($item);
                continue;
            }
            $normalizedKey = $key === 'field' ? 'column' : ($key === 'fields' ? 'columns' : $key);
            if (is_array($item)) {
                $item = array_is_list($item)
                    ? array_map(fn ($child) => is_array($child) ? $this->normalizeExpressionKeys($child) : $child, $item)
                    : $this->normalizeExpressionKeys($item);
            }
            $normalized[$normalizedKey] = $item;
        }
        return $normalized;
    }

    private function normalizeFilter(array $filter): array
    {
        $normalized = ['operator' => strtoupper($filter['operator'])];
        if (isset($filter['field'])) { $normalized['column'] = $filter['field']; }
        if (array_key_exists('value', $filter)) { $normalized['value'] = $filter['value']; }
        if (isset($filter['query'])) { $normalized['subquery'] = $this->normalizeSelect($filter['query']); }
        return $normalized;
    }

    private function normalizeSort(array $sort): array
    {
        return array_map(fn (array $item) => [
            'column' => $item['field'],
            'direction' => strtoupper($item['direction'] ?? 'ASC')
        ], $sort);
    }
}
