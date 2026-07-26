<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../core/QueryEngine.php';

class MetadataRepository
{
    private QueryEngine $queryEngine;

    public function __construct()
    {
        $this->queryEngine = new QueryEngine();
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
            [$tableName]
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
           [$table]
        );

    return (($result["data"][0]["Total"] ?? 0) > 0);
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
        ]
    );

    return (($result["data"][0]["Total"] ?? 0) > 0);
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