<?php

require_once __DIR__ . '/../Repositories/WriteRepository.php';

class WriteService
{
    private WriteRepository $writeRepository;

    public function __construct(?WriteRepository $writeRepository = null)
    {
        $this->writeRepository = $writeRepository ?? new WriteRepository();
    }

    public function execute(array $request): array
    {
        return $this->writeRepository->execute($request);
    }
}
