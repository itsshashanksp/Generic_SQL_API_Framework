<?php

require_once __DIR__ . '/../Repositories/QueryRepository.php';

class QueryService
{
    private QueryRepository $queryRepository;

    public function __construct()
    {
        $this->queryRepository = new QueryRepository();
    }

    /*
    |--------------------------------------------------------------------------
    | Select Service
    |--------------------------------------------------------------------------
    */
    public function select($request)
    {
        return $this->queryRepository->select($request);
    }

    /*
    |--------------------------------------------------------------------------
    | Union Service
    |--------------------------------------------------------------------------
    */
    public function union($request)
    {
        return $this->queryRepository->select($request);
    }

    /*
    |--------------------------------------------------------------------------
    | Procedure Service
    |--------------------------------------------------------------------------
    */
    public function procedure($request)
    {
        return $this->queryRepository->procedure($request);
    }

    /*
    |--------------------------------------------------------------------------
    | Function Service
    |--------------------------------------------------------------------------
    */
    public function function($request)
    {
        return $this->queryRepository->function($request);
    }

    /*
    |--------------------------------------------------------------------------
    | Table Function Service
    |--------------------------------------------------------------------------
    */
    public function tableFunction($request)
    {
        return $this->queryRepository->tableFunction($request);
    }
}