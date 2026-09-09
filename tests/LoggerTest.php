<?php

require_once __DIR__ . '/../core/Logger.php';

function loggerAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$warnings = [];
set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
    $warnings[] = [$severity, $message];
    return true;
});

$logDirectory = sys_get_temp_dir() . '/generic-dashboard-logger-tests-' . bin2hex(random_bytes(6));
$logger = new Logger($logDirectory);
$logger->write('normal-write');
$logger->write('second-write');
$logger->timing('logger_test', 1.25, ['marker' => 'correlation-preserved']);

$workers = [];
$workerCount = 4;
$entriesPerWorker = 30;
for ($worker = 0; $worker < $workerCount; $worker++) {
    $requestId = 'logger-worker-' . $worker;
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/LoggerConcurrentWriter.php', $logDirectory, $requestId, (string)$entriesPerWorker],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    loggerAssert(is_resource($process), 'Unable to start concurrent logger worker.');
    fclose($pipes[0]);
    $workers[] = [$process, $pipes, $requestId];
}

foreach ($workers as [$process, $pipes, $requestId]) {
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    loggerAssert($status === 0, "Logger worker {$requestId} failed: {$stdout}{$stderr}");
    loggerAssert($stderr === '', "Logger worker {$requestId} emitted a warning: {$stderr}");
}

$logFile = $logDirectory . '/' . date('Y-m-d') . '.log';
$lines = file($logFile, FILE_IGNORE_NEW_LINES);
loggerAssert(is_array($lines), 'Normal log file could not be read.');
loggerAssert(in_array('normal-write', $lines, true), 'Normal log write was lost.');
loggerAssert(in_array('second-write', $lines, true), 'A repeated log write was lost.');

$timingLines = [];
foreach ($lines as $line) {
    if (!str_starts_with($line, '{')) continue;
    $record = json_decode($line, true);
    loggerAssert(is_array($record), 'Concurrent writes produced an interleaved/corrupt JSON line.');
    if (($record['phase'] ?? null) === 'concurrent_test') $timingLines[] = $record;
}
loggerAssert(
    count($timingLines) === $workerCount * $entriesPerWorker,
    'Concurrent logging lost one or more entries.'
);
foreach (range(0, $workerCount - 1) as $worker) {
    $requestId = 'logger-worker-' . $worker;
    $matches = array_filter($timingLines, static fn (array $entry): bool => ($entry['requestId'] ?? null) === $requestId);
    loggerAssert(count($matches) === $entriesPerWorker, "Correlation ID {$requestId} was lost or altered.");
}

// Exercise the Windows-compatible atomic-append fallback under real process
// contention as well as the primary explicit-flock path above.
$fallbackDirectory = $logDirectory . '/atomic-append-fallback';
$fallbackWorkers = [];
for ($worker = 0; $worker < $workerCount; $worker++) {
    $pipes = [];
    $environment = getenv();
    $environment['LOGGER_TEST_ATOMIC_APPEND'] = '1';
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/LoggerConcurrentWriter.php', $fallbackDirectory, 'fallback-' . $worker, (string)$entriesPerWorker],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        $environment
    );
    loggerAssert(is_resource($process), 'Unable to start atomic-append logger worker.');
    fclose($pipes[0]);
    $fallbackWorkers[] = [$process, $pipes];
}
foreach ($fallbackWorkers as [$process, $pipes]) {
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    loggerAssert(proc_close($process) === 0, "Atomic-append logger worker failed: {$stdout}{$stderr}");
    loggerAssert($stderr === '', "Atomic-append logger worker emitted a warning: {$stderr}");
}
$fallbackLines = file($fallbackDirectory . '/' . date('Y-m-d') . '.log', FILE_IGNORE_NEW_LINES);
loggerAssert(is_array($fallbackLines) && count($fallbackLines) === $workerCount * $entriesPerWorker, 'Atomic append lost entries.');
foreach ($fallbackLines as $line) {
    loggerAssert(is_array(json_decode($line, true)), 'Atomic append produced an interleaved/corrupt entry.');
}

$joinedLog = implode("\n", $lines);
loggerAssert(str_contains($joinedLog, '"phase":"logger_test"'), 'Timing logging was removed.');
loggerAssert(str_contains($joinedLog, 'correlation-preserved'), 'Timing context was not preserved.');
loggerAssert($logger->safeSql("SELECT * FROM T WHERE Secret = 'value' AND Id = 42")
    === "SELECT * FROM T WHERE Secret = '[REDACTED]' AND Id = #", 'Safe SQL redaction changed.');

// A regular file cannot also be a parent directory. This forces both directory
// creation and log opening to fail and verifies diagnostics never crash the API.
$blockedPath = $logDirectory . '/not-a-directory';
file_put_contents($blockedPath, 'block');
$failedLogger = new Logger($blockedPath . '/child');
$failedLogger->write('must-not-throw');

restore_error_handler();
loggerAssert($warnings === [], 'Logger emitted a PHP warning: ' . json_encode($warnings));

echo "Logger tests passed.\n";
