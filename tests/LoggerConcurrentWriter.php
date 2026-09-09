<?php

require_once __DIR__ . '/../core/Logger.php';

if ($argc !== 4) {
    exit(2);
}

define('API_REQUEST_ID', $argv[2]);

class ConcurrentTestLogger extends Logger
{
    protected function acquireLock($stream): bool
    {
        return getenv('LOGGER_TEST_ATOMIC_APPEND') === '1'
            ? false
            : parent::acquireLock($stream);
    }
}

$logger = new ConcurrentTestLogger($argv[1]);
$count = (int)$argv[3];

for ($index = 0; $index < $count; $index++) {
    $logger->timing('concurrent_test', (float)$index, ['sequence' => $index]);
}
