<?php

require_once __DIR__ . '/../Repositories/QueryRepository.php';

class QueryService
{
    private QueryRepository $queryRepository;

    public function __construct()
    {
        $this->queryRepository = new QueryRepository();
    }

    public function select($request)
    {
        return $this->queryRepository->select($request);
    }
}