<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/DatabaseService.php';

class DatabaseController extends BaseController
{
    private DatabaseService $databaseService;

    public function __construct()
    {
        $this->databaseService = new DatabaseService();
    }

    /**
     * Get Tables
     */
    public function tables($request)
    {

        $tables = $this->databaseService->getTables();

        $this->success(
            $tables,
            "Tables Loaded Successfully"
        );
    }

    /**
     * Get Databases
     */
    public function databases($request)
    {
        $databases = $this->databaseService->getDatabases();

        $this->success(
            $databases,
            "Databases Loaded Successfully"
        );
    }
}
