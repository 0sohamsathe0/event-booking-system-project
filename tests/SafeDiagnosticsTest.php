<?php

declare(strict_types=1);

use App\Core\SafeDiagnostics;

require dirname(__DIR__) . '/bootstrap/app.php';

function assertSafeDiagnostics(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$databaseError = new PDOException('Sensitive provider message must not be rendered.');
$databaseError->errorInfo = ['HY000', 2002, 'Sensitive provider message'];
$wrapped = new RuntimeException('Unable to connect to the database.', 0, $databaseError);

assertSafeDiagnostics(
    SafeDiagnostics::category($wrapped) === 'database_connection_failed',
    'Database connection errors were categorized incorrectly.'
);

$authenticationError = new PDOException('Access denied for a private user.');
$authenticationError->errorInfo = ['28000', 1045, 'Access denied'];
assertSafeDiagnostics(
    SafeDiagnostics::category($authenticationError) === 'database_authentication_failed',
    'Database authentication errors were categorized incorrectly.'
);

putenv('APP_SAFE_DIAGNOSTICS=false');
assertSafeDiagnostics(
    SafeDiagnostics::unavailable($wrapped) === 'unavailable',
    'Disabled diagnostics exposed a failure category.'
);

putenv('APP_SAFE_DIAGNOSTICS=true');
$rendered = SafeDiagnostics::unavailable($wrapped);
assertSafeDiagnostics(
    $rendered === 'unavailable: database_connection_failed',
    'Enabled diagnostics did not render the safe category.'
);
assertSafeDiagnostics(
    !str_contains($rendered, 'Sensitive') && !str_contains($rendered, 'private user'),
    'Safe diagnostics exposed an exception message.'
);

putenv('APP_SAFE_DIAGNOSTICS');
fwrite(STDOUT, "Safe diagnostics tests passed.\n");
