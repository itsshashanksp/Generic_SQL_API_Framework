<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/SqlService.php';

class SQLController extends BaseController
{
    private SqlService $sqlService;

    public function __construct(?SqlService $sqlService = null)
    {
        $this->sqlService = $sqlService ?? new SqlService();
    }

    public function execute(array $request): void
    {
        $this->success(
            $this->sqlService->execute($request),
            'Data Loaded Successfully'
        );
    }
}
