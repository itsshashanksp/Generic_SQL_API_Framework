<?php

class Logger
{
    private string $logDirectory;
    private string $logFile;

    public function __construct(?string $logDirectory = null)
    {
        $this->logDirectory = $logDirectory ?? __DIR__ . "/../logs";

        if (!is_dir($this->logDirectory)) {
            $this->attempt(fn () => mkdir($this->logDirectory, 0777, true));
        }

        $this->logFile =
            $this->logDirectory .
            "/" .
            date("Y-m-d") .
            ".log";
    }

    public function write(string $message): void
    {
        $entry = $message . PHP_EOL;

        $this->attempt(function () use ($entry): void {
            $stream = fopen($this->logFile, 'ab');
            if ($stream === false) {
                throw new RuntimeException('Unable to open the application log.');
            }

            $locked = false;
            try {
                $locked = $this->acquireLock($stream);
                $length = strlen($entry);

                if ($locked) {
                    $written = 0;
                    while ($written < $length) {
                        $bytes = fwrite($stream, substr($entry, $written));
                        if ($bytes === false || $bytes === 0) {
                            throw new RuntimeException('Unable to complete the application log write.');
                        }
                        $written += $bytes;
                    }
                } else {
                    // A local file opened with append mode uses the platform's
                    // atomic append operation. Keep this to one write so entries
                    // cannot be split/interleaved when advisory locks are absent.
                    $written = fwrite($stream, $entry);
                    if ($written !== $length) {
                        throw new RuntimeException('Unable to atomically append the application log entry.');
                    }
                }

                if (!fflush($stream)) {
                    throw new RuntimeException('Unable to flush the application log.');
                }
            } finally {
                if ($locked) {
                    $this->releaseLock($stream);
                }
                fclose($stream);
            }
        });
    }

    protected function acquireLock($stream): bool
    {
        return flock($stream, LOCK_EX);
    }

    protected function releaseLock($stream): void
    {
        flock($stream, LOCK_UN);
    }

    /**
     * Logging is diagnostic and must not turn an API response into a failure.
     * Capture filesystem warnings locally while preserving the caller's handler.
     */
    private function attempt(callable $operation): bool
    {
        set_error_handler(static function (
            int $severity,
            string $message,
            string $file,
            int $line
        ): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $result = $operation();
            return $result !== false;
        } catch (Throwable $exception) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    public function timing(string $phase, float $elapsedMilliseconds, array $context = []): void
    {
        $record = [
            'timestamp' => date(DATE_ATOM),
            'requestId' => defined('API_REQUEST_ID') ? API_REQUEST_ID : null,
            'event' => 'timing',
            'phase' => $phase,
            'elapsedMs' => round($elapsedMilliseconds, 2),
        ] + $context;

        $this->write((string)json_encode($record, JSON_UNESCAPED_SLASHES));
    }

    public function safeSql(string $sql): string
    {
        // Builders normally use placeholders, but redact any literal that an
        // approved SQL resource or expression may have embedded in the text.
        $sql = (string)preg_replace("/'(?:''|[^'])*'/", "'[REDACTED]'", $sql);
        $sql = (string)preg_replace('/(?<![A-Za-z0-9_])[-+]?\d+(?:\.\d+)?(?![A-Za-z0-9_])/', '#', $sql);
        $sql = trim((string)preg_replace('/\s+/', ' ', $sql));
        return strlen($sql) > 4000 ? substr($sql, 0, 4000) . ' ...' : $sql;
    }

    public function parameterMetadata(array $params): array
    {
        return [
            'count' => count($params),
            'types' => array_map(static fn ($value): string => get_debug_type($value), $params),
        ];
    }

        public function error(
        string $sql,
        array $params,
        string $error,
        float $executionTime = 0
    ): void {

        $message =
            "========================================\n";

        $message .=
            "Date : "
            . date("Y-m-d H:i:s")
            . "\n";

        $message .=
            "Status : ERROR\n";

        $message .=
            "Execution Time : "
            . round($executionTime, 2)
            . " ms\n\n";

        $message .=
            "SQL:\n"
            . $this->safeSql($sql)
            . "\n\n";

        $message .=
            "Parameters:\n"
            . json_encode(
                $this->parameterMetadata($params),
                JSON_PRETTY_PRINT
            )
            . "\n\n";

        $message .=
            "Error:\n"
            . $error
            . "\n";

        $message .=
            "========================================\n";

        $this->write($message);
    }
}
