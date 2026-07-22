<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/QueryService.php';
require_once __DIR__ . '/../../core/Validator.php';

class QueryController extends BaseController
{
    private QueryService $queryService;

    public function __construct()
    {
        $this->queryService = new QueryService();
    }

    public function select($request)
    {
        Validator::required($request, [
            'table',
            'columns'
        ]);

        $this->success(
            $this->queryService->select($request),
            "Data Loaded Successfully"
        );
    }
}