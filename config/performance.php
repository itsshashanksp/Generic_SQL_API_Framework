<?php

$queryTimeout = getenv('DB_QUERY_TIMEOUT_SECONDS');
$queryTimeout = $queryTimeout === false ? 45 : (int)$queryTimeout;

return [
    // Keep below PHP max_execution_time so the API can serialize timeout errors.
    'database_query_timeout_seconds' => max(1, $queryTimeout),
];
