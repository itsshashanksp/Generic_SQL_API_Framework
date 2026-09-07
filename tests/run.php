<?php

$tests = [
    __DIR__ . '/UniversalApiContractTest.php',
    __DIR__ . '/OrderByWindowRegressionTest.php',
    __DIR__ . '/BackendLogicTest.php',
    __DIR__ . '/SqlControllerTest.php'
];

foreach ($tests as $test) {
    echo 'Running ' . basename($test) . PHP_EOL;
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test);
    passthru($command, $status);
    if ($status !== 0) {
        fwrite(STDERR, basename($test) . " failed.\n");
        exit($status);
    }
}

echo "All database-independent backend tests passed.\n";
