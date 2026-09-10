<?php

class PaginationBuilder
{
    private QueryEngine $queryEngine;
    private ?int $sqlServerCompatibilityLevel = null;

    public function __construct(QueryEngine $queryEngine)
    {
        $this->queryEngine = $queryEngine;
    }

    public function apply(
        string $sql,
        string $sqlWithoutOrderBy,
        array $params,
        array $request,
        ?string $paginationOrderBy,
        bool $paginateData = true,
        string $queryPrefix = ''
    ): array {
        $totalRows = null;

        if (isset($request['page']) && isset($request['pageSize'])) {
            $countSql = $queryPrefix . "SELECT COUNT(*) AS TotalRows
         FROM (
             {$sqlWithoutOrderBy}
         ) AS CountQuery";
            $countResult = $this->queryEngine->executePrepared($countSql, $params, [
                'queryPhase' => 'pagination_count',
                'page' => (int)$request['page'],
                'pageSize' => (int)$request['pageSize'],
            ]);
            if (!empty($countResult['data']) && isset($countResult['data'][0]['TotalRows'])) {
                $totalRows = (int)$countResult['data'][0]['TotalRows'];
            } else {
                $totalRows = 0;
            }
        }

        if ($paginateData && isset($request['page']) && isset($request['pageSize'])) {
            $page = max(1, (int)$request['page']);
            $pageSize = max(1, (int)$request['pageSize']);
            $offset = ($page - 1) * $pageSize;

            if ($this->getSqlServerCompatibilityLevel() >= 110) {
                $sql .= "
            OFFSET {$offset} ROWS
            FETCH NEXT {$pageSize} ROWS ONLY
        ";
            } else {
                $orderBy = $paginationOrderBy;
                $innerSql = $sqlWithoutOrderBy;
                $startRow = $offset + 1;
                $endRow = $offset + $pageSize;
                $sql = "
            SELECT *
            FROM
            (
                SELECT
                    PagedSource.*,
                    ROW_NUMBER() OVER (
                        {$orderBy}
                    ) AS __row_num
                FROM
                (
                    {$innerSql}
                ) AS PagedSource
            ) AS PagedQuery
            WHERE __row_num
                BETWEEN {$startRow}
                AND {$endRow}
            ORDER BY __row_num
        ";
            }
        }

        return ['sql' => $sql, 'totalRows' => $totalRows];
    }

    private function getSqlServerCompatibilityLevel(): int
    {
        if ($this->sqlServerCompatibilityLevel !== null) {
            return $this->sqlServerCompatibilityLevel;
        }
        $result = $this->queryEngine->executePrepared(
            "
            SELECT compatibility_level AS CompatibilityLevel
            FROM sys.databases
            WHERE name = DB_NAME()
            ",
            [],
            ['queryPhase' => 'metadata']
        );
        if (empty($result['data']) || !isset($result['data'][0]['CompatibilityLevel'])) {
            throw new Exception('Unable to determine SQL Server compatibility level.');
        }
        $this->sqlServerCompatibilityLevel = (int)$result['data'][0]['CompatibilityLevel'];
        return $this->sqlServerCompatibilityLevel;
    }
}
