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
}