<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/MetadataService.php';

class MetadataController extends BaseController
{
    private MetadataService $metadataService;

    public function __construct()
    {
        $this->metadataService = new MetadataService();
    }

    /**
     * Get Tables
     */
    public function tables($request)
    {
        $this->success(
            $this->metadataService->getTables(),
            "Tables Loaded Successfully"
        );
    }

    /**
     * Get Columns
     */
    public function columns($request)
    {
        Validator::required($request, [
            'table'
        ]);

        $this->success(
            $this->metadataService->getColumns(
                $request['table']
            ),
            "Columns Loaded Successfully"
        );
    }

    /**
    * Get Views
    */
    public function views($request)
    {
        $this->success(
           $this->metadataService->getViews(),
        "Views Loaded Successfully"
           );
    }

    /**
    * Get Stored Procedures
    */
    public function procedures($request)
    {
        $this->success(
           $this->metadataService->getProcedures(),
        "Stored Procedures Loaded Successfully"
           );
    }

    /**
    * Get Schema
    */
    public function schema($request)
    {
        $this->success(
           $this->metadataService->schema(),
        "Schema Loaded Successfully"
           );
    }
}