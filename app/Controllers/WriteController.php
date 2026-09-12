<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/WriteService.php';

class WriteController extends BaseController
{
    private WriteService $writeService;

    public function __construct(?WriteService $writeService = null)
    {
        $this->writeService = $writeService ?? new WriteService();
    }

    public function insert(array $request): void { $this->respond($request, 'Data Inserted Successfully'); }
    public function update(array $request): void { $this->respond($request, 'Data Updated Successfully'); }
    public function delete(array $request): void { $this->respond($request, 'Data Deleted Successfully'); }
    public function upsert(array $request): void { $this->respond($request, 'Data Upserted Successfully'); }

    private function respond(array $request, string $message): void
    {
        $this->success($this->writeService->execute($request), $message);
    }
}
