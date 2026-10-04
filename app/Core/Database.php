<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static array $config = [];
    private static ?PDO $connection = null;

    private function __construct()
    {
    }

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        if (self::$config === []) {
            throw new RuntimeException('Database configuration has not been loaded.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::$config['host'],
            self::$config['port'],
            self::$config['database'],
            self::$config['charset']
        );

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => false,
            ];
            $sslMode = strtolower((string) (self::$config['ssl_mode'] ?? 'disabled'));
            $sslCa = trim((string) (self::$config['ssl_ca'] ?? ''));
            if ($sslMode !== 'disabled') {
                $options[PDO::MYSQL_ATTR_SSL_CIPHER] = 'DEFAULT';
                if ($sslCa !== '') {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
                }
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = in_array(
                    $sslMode,
                    ['verify_ca', 'verify_identity'],
                    true
                );
            }

            self::$connection = new PDO(
                $dsn,
                self::$config['username'],
                self::$config['password'],
                $options
            );

            self::$connection->exec("SET time_zone = '+00:00'");
            if (in_array($sslMode, ['required', 'verify_ca', 'verify_identity'], true)) {
                $status = self::$connection->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch();
                if (!is_array($status) || trim((string) ($status['Value'] ?? '')) === '') {
                    self::$connection = null;
                    throw new RuntimeException('The database did not establish the required encrypted connection.');
                }
            }
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to the database.', 0, $exception);
        }

        return self::$connection;
    }
}
