<?php

require_once __DIR__ . '/../Repositories/MetadataRepository.php';

class MetadataService
{
    private MetadataRepository $metadataRepository;

    public function __construct()
    {
        $this->metadataRepository = new MetadataRepository();
    }

    /**
     * Get Tables
     */
    public function getTables()
    {
        return $this->metadataRepository->getTables();
    }

    /**
     * Get Columns
     */
    public function getColumns($tableName)
    {
        return $this->metadataRepository->getColumns(
            $tableName
        );
    }

    /**
    * Get Views
    */
    public function getViews()
    {
        return $this->metadataRepository->getViews();
    }

    /**
    * Get Stored Procedures
    */
    public function getProcedures()
    {
        return $this->metadataRepository->getProcedures();
    }

    /**
    * Check Table Exists
    */
    public function tableExists($table)
    {
        return $this->metadataRepository->tableExists($table);
    }

    /**
    * Check Column Exists
    */
    public function columnExists($table, $column)
    {
        return $this->metadataRepository->columnExists(
        $table,
        $column
        );
    }

    /**
    * Get Schema
    */
    public function schema()
    {
        return $this->metadataRepository->schema();
    }

}