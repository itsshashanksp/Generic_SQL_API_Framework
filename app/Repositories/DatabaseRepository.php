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

    public function getTables()
    {
        return $this->queryEngine->executeFile(
            QUERY_PATH . '/system/Tables.sql'
        );
    }
}