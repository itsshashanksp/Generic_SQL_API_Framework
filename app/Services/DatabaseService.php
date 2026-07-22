<?php

require_once __DIR__ . '/../Repositories/DatabaseRepository.php';

class DatabaseService
{
    private DatabaseRepository $repository;

    public function __construct()
    {
        $this->repository = new DatabaseRepository();
    }

    public function getDatabases()
    {
        return $this->repository->getDatabases();
    }

    public function getTables()
    {
        return $this->repository->getTables();
    }
}
