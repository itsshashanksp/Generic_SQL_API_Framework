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

    /*
    |--------------------------------------------------------------------------
    | Select Controller
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Union Controller
    |--------------------------------------------------------------------------
    */
    public function union($request)
    {
        Validator::required($request, [
            'queries'
        ]);

        $this->success(
            $this->queryService->union($request),
            "Data Loaded Successfully"
      );
    }

    /*
    |--------------------------------------------------------------------------
    | Procedure Controller
    |--------------------------------------------------------------------------
    */
    public function procedure($request)
    {
        Validator::required($request, [
            "procedure"
        ]);

        $this->success(
            $this->queryService->procedure($request),
            "Procedure Executed Successfully"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Function Controller
    |--------------------------------------------------------------------------
    */
    public function function($request)
    {
        Validator::required($request, [
            "function"
        ]);

        $this->success(
            $this->queryService->function($request),
            "Function Executed Successfully"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Table Function Controller
    |--------------------------------------------------------------------------
    */
    public function tableFunction($request)
    {
        Validator::required($request, [
            "function"
        ]);

        $this->success(
            $this->queryService->tableFunction($request),
            "Table Function Executed Successfully"
        );
    }
}