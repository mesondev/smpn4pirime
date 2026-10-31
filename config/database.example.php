<?php

declare(strict_types=1);

// Copy this file to config/database.php, then configure environment variables
// in Apache/PHP-FPM. Never put production credentials in this example file.
$environment = strtolower((string)(getenv('SIMS_APP_ENV') ?: 'local'));
$hostValue = getenv('SIMS_DB_HOST');
$databaseValue = getenv('SIMS_DB_NAME');
$userValue = getenv('SIMS_DB_USER');
$passwordValue = getenv('SIMS_DB_PASS');

$host = $hostValue === false ? ($environment === 'local' ? '127.0.0.1' : '') : (string)$hostValue;
$database = $databaseValue === false ? ($environment === 'local' ? 'sims_db' : '') : (string)$databaseValue;
$user = $userValue === false ? ($environment === 'local' ? 'root' : '') : (string)$userValue;
$password = $passwordValue === false ? '' : (string)$passwordValue;
$portValue = getenv('SIMS_DB_PORT');
$port = $portValue === false || $portValue === '' ? 3306 : filter_var($portValue, FILTER_VALIDATE_INT);

if ($host === '' || $database === '' || $user === '' || !is_int($port) || $port < 1 || $port > 65535 || ($environment === 'production' && $password === '')) {
    http_response_code(500);
    if (function_exists('app_log')) app_log('error', 'Database configuration is incomplete');
    else error_log('[SIMS] Database configuration is incomplete.');
    exit('Konfigurasi database belum lengkap. Hubungi administrator.');
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $exception) {
    http_response_code(500);
    if (function_exists('app_log')) app_log('error', 'Database connection failed', ['type' => get_class($exception)]);
    else error_log('[SIMS] Database connection failed.');
    exit('Koneksi database gagal. Hubungi administrator.');
}
