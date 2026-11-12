<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$level = (string)($_GET['level'] ?? '');
$parent = (string)($_GET['parent'] ?? '');
$routes = [
    'provinces' => ['pattern' => '/^$/', 'path' => 'provinces.json'],
    'regencies' => ['pattern' => '/^\d{2}$/', 'path' => 'regencies/%s.json'],
    'districts' => ['pattern' => '/^\d{2}\.\d{2}$/', 'path' => 'districts/%s.json'],
    'villages' => ['pattern' => '/^\d{2}\.\d{2}\.\d{2}$/', 'path' => 'villages/%s.json'],
];

// Keep province selection and the Papua Pegunungan regency list available
// when the XAMPP host cannot reach wilayah.id.
$localFallbacks = [
    'provinces' => [
        '' => [
            ['code' => '11', 'name' => 'Aceh'], ['code' => '12', 'name' => 'Sumatera Utara'],
            ['code' => '13', 'name' => 'Sumatera Barat'], ['code' => '14', 'name' => 'Riau'],
            ['code' => '15', 'name' => 'Jambi'], ['code' => '16', 'name' => 'Sumatera Selatan'],
            ['code' => '17', 'name' => 'Bengkulu'], ['code' => '18', 'name' => 'Lampung'],
            ['code' => '19', 'name' => 'Kepulauan Bangka Belitung'], ['code' => '21', 'name' => 'Kepulauan Riau'],
            ['code' => '31', 'name' => 'DKI Jakarta'], ['code' => '32', 'name' => 'Jawa Barat'],
            ['code' => '33', 'name' => 'Jawa Tengah'], ['code' => '34', 'name' => 'Daerah Istimewa Yogyakarta'],
            ['code' => '35', 'name' => 'Jawa Timur'], ['code' => '36', 'name' => 'Banten'],
            ['code' => '51', 'name' => 'Bali'], ['code' => '52', 'name' => 'Nusa Tenggara Barat'],
            ['code' => '53', 'name' => 'Nusa Tenggara Timur'], ['code' => '61', 'name' => 'Kalimantan Barat'],
            ['code' => '62', 'name' => 'Kalimantan Tengah'], ['code' => '63', 'name' => 'Kalimantan Selatan'],
            ['code' => '64', 'name' => 'Kalimantan Timur'], ['code' => '65', 'name' => 'Kalimantan Utara'],
            ['code' => '71', 'name' => 'Sulawesi Utara'], ['code' => '72', 'name' => 'Sulawesi Tengah'],
            ['code' => '73', 'name' => 'Sulawesi Selatan'], ['code' => '74', 'name' => 'Sulawesi Tenggara'],
            ['code' => '75', 'name' => 'Gorontalo'], ['code' => '76', 'name' => 'Sulawesi Barat'],
            ['code' => '81', 'name' => 'Maluku'], ['code' => '82', 'name' => 'Maluku Utara'],
            ['code' => '91', 'name' => 'Papua'], ['code' => '92', 'name' => 'Papua Barat'],
            ['code' => '93', 'name' => 'Papua Selatan'], ['code' => '94', 'name' => 'Papua Tengah'],
            ['code' => '95', 'name' => 'Papua Pegunungan'], ['code' => '96', 'name' => 'Papua Barat Daya'],
        ],
    ],
    'regencies' => [
        '95' => [
            ['code' => '95.01', 'name' => 'Kabupaten Jayawijaya'],
            ['code' => '95.02', 'name' => 'Kabupaten Pegunungan Bintang'],
            ['code' => '95.03', 'name' => 'Kabupaten Yahukimo'],
            ['code' => '95.04', 'name' => 'Kabupaten Tolikara'],
            ['code' => '95.05', 'name' => 'Kabupaten Mamberamo Tengah'],
            ['code' => '95.06', 'name' => 'Kabupaten Yalimo'],
            ['code' => '95.07', 'name' => 'Kabupaten Lanny Jaya'],
            ['code' => '95.08', 'name' => 'Kabupaten Nduga'],
        ],
    ],
];

if (!isset($routes[$level]) || !preg_match($routes[$level]['pattern'], $parent)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_region_query']);
    exit;
}

$relativePath = $parent === '' ? $routes[$level]['path'] : sprintf($routes[$level]['path'], $parent);
$cacheDirectory = dirname(__DIR__) . '/storage/cache/regions';
$cacheFile = $cacheDirectory . '/' . hash('sha256', $relativePath) . '.json';
$cacheLifetime = 30 * 24 * 60 * 60;
$cachedJson = is_file($cacheFile) ? @file_get_contents($cacheFile) : false;
$cachedAt = is_file($cacheFile) ? (int)@filemtime($cacheFile) : 0;

if (is_string($cachedJson) && $cachedJson !== '' && $cachedAt >= time() - $cacheLifetime) {
    echo $cachedJson;
    exit;
}

$remoteUrl = 'https://wilayah.id/api/' . $relativePath;
$remoteJson = false;
if (function_exists('curl_init')) {
    $curl = curl_init($remoteUrl);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'SIMS-SMPN4-Region-Selector/1.0',
    ]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($response !== false && $status === 200) $remoteJson = $response;
}

$payload = is_string($remoteJson) ? json_decode($remoteJson, true) : null;
$validData = is_array($payload['data'] ?? null);
if ($validData) {
    foreach ($payload['data'] as $item) {
        if (!is_array($item) || !isset($item['code'], $item['name']) || !is_string($item['code']) || !is_string($item['name'])) {
            $validData = false;
            break;
        }
    }
}

if ($validData) {
    $normalizedJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (is_string($normalizedJson)) {
        if (!is_dir($cacheDirectory)) @mkdir($cacheDirectory, 0750, true);
        if (is_dir($cacheDirectory) && is_writable($cacheDirectory)) @file_put_contents($cacheFile, $normalizedJson, LOCK_EX);
        echo $normalizedJson;
        exit;
    }
}

if (is_string($cachedJson) && $cachedJson !== '') {
    header('X-Region-Data: stale-cache');
    echo $cachedJson;
    exit;
}

if (isset($localFallbacks[$level][$parent])) {
    header('X-Region-Data: local-fallback');
    echo json_encode(['data' => $localFallbacks[$level][$parent]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(502);
echo json_encode(['error' => 'region_source_unavailable']);
