<?php

require_once __DIR__ . '/../Repositories/SqlRepository.php';

class SqlService
{
    private SqlRepository $repository;

    public function __construct(?SqlRepository $repository = null)
    {
        $this->repository = $repository ?? new SqlRepository();
    }

    public function execute(array $request): array
    {
        return $this->repository->execute($request);
    }
}
