<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../core/QueryEngine.php';

class MetadataRepository
{
    private QueryEngine $queryEngine;

    public function __construct(?QueryEngine $queryEngine = null)
    {
        $this->queryEngine = $queryEngine ?? new QueryEngine();
    }

    /**
     * Get Tables
     */
    public function getTables()
    {
        return $this->queryEngine->executeFile(
            QUERY_PATH . '/system/Tables.sql'
        );
    }

    /**
     * Get Columns
     */
    public function getColumns($tableName)
    {
        $sql = $this->queryEngine->getQuery(
            QUERY_PATH . '/system/Columns.sql'
        );

        return $this->queryEngine->executePrepared(
            $sql,
            [$tableName],
            ['queryPhase' => 'metadata']
        );
    }

    /**
     * Get Views
     */
    public function getViews()
    {
        return $this->queryEngine->executeFile(
            QUERY_PATH . '/system/Views.sql'
        );
    }

    /**
     * Get Stored Procedures
     */
    public function getProcedures()
    {
        return $this->queryEngine->executeFile(
            QUERY_PATH . '/system/Procedures.sql'
        );
    }

    /**
     * Check Table Exists
     */
    public function tableExists($table)
    {
        $sql = "
            SELECT
                COUNT(*) AS Total
            FROM
                INFORMATION_SCHEMA.TABLES
            WHERE
                TABLE_NAME = ?
        ";

        $result = $this->queryEngine->executePrepared(
            $sql,
            [$table],
            ['queryPhase' => 'metadata']
        );

        return (
            ($result["data"][0]["Total"] ?? 0) > 0
        );
    }

    /**
     * Check Column Exists
     */
    public function columnExists($table, $column)
    {
        $sql = "
            SELECT
                COUNT(*) AS Total
            FROM
                INFORMATION_SCHEMA.COLUMNS
            WHERE
                TABLE_NAME = ?
            AND
                COLUMN_NAME = ?
        ";

        $result = $this->queryEngine->executePrepared(
            $sql,
            [
                $table,
                $column
            ],
            ['queryPhase' => 'metadata']
        );

        return (
            ($result["data"][0]["Total"] ?? 0) > 0
        );
    }

    /**
     * Get Column Data Type
     *
     * Returns the SQL Server DATA_TYPE for
     * the specified table column.
     */
    public function getColumnDataType(
        $table,
        $column
    ) {
        $sql = "
            SELECT
                DATA_TYPE
            FROM
                INFORMATION_SCHEMA.COLUMNS
            WHERE
                TABLE_NAME = ?
            AND
                COLUMN_NAME = ?
        ";

        $result = $this->queryEngine->executePrepared(
            $sql,
            [
                $table,
                $column
            ],
            ['queryPhase' => 'metadata']
        );

        return $result["data"][0]["DATA_TYPE"]
            ?? null;
    }

    /**
     * Return SQL Server column properties required to validate safe writes.
     */
    public function getWriteColumns(string $schema, string $table): array
    {
        $sql = "
            SELECT
                c.name AS ColumnName,
                ty.name AS DataType,
                c.max_length AS MaxLength,
                c.precision AS NumericPrecision,
                c.scale AS NumericScale,
                c.is_nullable AS IsNullable,
                c.is_identity AS IsIdentity,
                c.is_computed AS IsComputed,
                c.generated_always_type AS GeneratedAlwaysType,
                c.is_hidden AS IsHidden,
                CASE WHEN c.default_object_id = 0 THEN 0 ELSE 1 END AS HasDefault
            FROM sys.columns c
            INNER JOIN sys.tables t ON t.object_id = c.object_id
            INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
            INNER JOIN sys.types ty ON ty.user_type_id = c.user_type_id
            WHERE s.name = ? AND t.name = ?
            ORDER BY c.column_id
        ";

        return $this->queryEngine->executePrepared(
            $sql,
            [$schema, $table],
            ['queryPhase' => 'metadata', 'action' => 'write']
        );
    }

    public function hasUniqueKey(string $schema, string $table, array $columns): bool
    {
        $sql = "
            SELECT
                i.name AS IndexName,
                c.name AS ColumnName,
                ic.key_ordinal AS KeyOrdinal
            FROM sys.indexes i
            INNER JOIN sys.tables t ON t.object_id = i.object_id
            INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
            INNER JOIN sys.index_columns ic
                ON ic.object_id = i.object_id AND ic.index_id = i.index_id
            INNER JOIN sys.columns c
                ON c.object_id = ic.object_id AND c.column_id = ic.column_id
            WHERE s.name = ? AND t.name = ?
                AND i.is_unique = 1 AND i.is_hypothetical = 0
                AND i.has_filter = 0 AND ic.key_ordinal > 0
            ORDER BY i.index_id, ic.key_ordinal
        ";
        $result = $this->queryEngine->executePrepared(
            $sql,
            [$schema, $table],
            ['queryPhase' => 'metadata', 'action' => 'upsert']
        );
        $indexes = [];
        foreach ($result['data'] ?? [] as $row) {
            $index = $this->metadataValue($row, 'IndexName');
            $column = $this->metadataValue($row, 'ColumnName');
            if (is_string($index) && is_string($column)) {
                $indexes[$index][] = strtolower($column);
            }
        }
        $expected = array_map('strtolower', $columns);
        sort($expected);
        foreach ($indexes as $indexColumns) {
            sort($indexColumns);
            if ($indexColumns === $expected) return true;
        }
        return false;
    }

    private function metadataValue(array $row, string $key)
    {
        foreach ($row as $name => $value) {
            if (strcasecmp((string)$name, $key) === 0) return $value;
        }
        return null;
    }

    /**
     * Get Schema
     */
    public function schema()
    {
        return $this->queryEngine->executeFile(
            QUERY_PATH . '/system/Schema.sql'
        );
    }
}
