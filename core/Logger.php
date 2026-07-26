<?php

class Logger
{
    private string $logDirectory;
    private string $logFile;

    public function __construct()
    {
        $this->logDirectory = __DIR__ . "/../logs";

        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0777, true);
        }

        $this->logFile =
            $this->logDirectory .
            "/" .
            date("Y-m-d") .
            ".log";
    }

    public function write(string $message): void
    {
        file_put_contents(
            $this->logFile,
            $message . PHP_EOL,
            FILE_APPEND
        );
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
            . $sql
            . "\n\n";

        $message .=
            "Parameters:\n"
            . json_encode(
                $params,
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