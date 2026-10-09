<?php

declare(strict_types=1);

use App\Core\EnvironmentLoader;

require dirname(__DIR__) . '/app/Core/EnvironmentLoader.php';

function assertEnvironmentLoader(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$keys = ['PHASE14_ENV_TEST', 'PHASE14_QUOTED_TEST', 'DB_SSL_CA'];
$original = [];
foreach ($keys as $key) {
    $original[$key] = getenv($key);
    putenv($key);
}

try {
    EnvironmentLoader::loadLines([
        '# comment',
        'PHASE14_ENV_TEST=value',
        'PHASE14_QUOTED_TEST="quoted value"',
        'DB_SSL_CA=-----BEGIN CERTIFICATE-----',
        'ZmFrZS1jZXJ0aWZpY2F0ZQ==',
        '-----END CERTIFICATE-----',
    ]);
    assertEnvironmentLoader(getenv('PHASE14_ENV_TEST') === 'value', 'Plain value was not loaded.');
    assertEnvironmentLoader(getenv('PHASE14_QUOTED_TEST') === 'quoted value', 'Quoted value was not loaded.');
    assertEnvironmentLoader(str_contains((string) getenv('DB_SSL_CA'), "\n"), 'Multiline certificate was not loaded.');

    putenv('PHASE14_ENV_TEST=process-value');
    EnvironmentLoader::loadLines(['PHASE14_ENV_TEST=file-value']);
    assertEnvironmentLoader(getenv('PHASE14_ENV_TEST') === 'process-value', 'Process environment precedence was lost.');

    fwrite(STDOUT, "Environment loader tests passed.\n");
} finally {
    foreach ($original as $key => $value) {
        putenv($value === false ? $key : $key . '=' . $value);
    }
}
