<?php

$tests = [
    __DIR__ . '/UniversalApiContractTest.php',
    __DIR__ . '/OrderByWindowRegressionTest.php',
    __DIR__ . '/BackendLogicTest.php',
    __DIR__ . '/Phase1CapabilityTest.php',
    __DIR__ . '/Phase2CrudTest.php',
    __DIR__ . '/SqlControllerTest.php',
    __DIR__ . '/QueryExecutionIsolationTest.php',
    __DIR__ . '/LoggerTest.php',
    __DIR__ . '/DatabaseCredentialEncryptionTest.php',
    __DIR__ . '/DatabaseConfigurationEncryptionTest.php'
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
