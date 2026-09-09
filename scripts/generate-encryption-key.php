<?php

try {
    $key = base64_encode(random_bytes(32));
    echo 'GENERIC_SQL_API_ENCRYPTION_KEY=' . $key . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Unable to generate an encryption key.\n");
    exit(1);
}
