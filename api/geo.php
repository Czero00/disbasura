<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

if ($type === 'barangays') {
    $cityId = (int)($_GET['city_id'] ?? 0);
    echo json_encode($cityId ? get_barangays($cityId) : []);
    exit;
}

if ($type === 'sitios') {
    $barangayId = (int)($_GET['barangay_id'] ?? 0);
    echo json_encode($barangayId ? get_sitios_by_barangay($barangayId) : []);
    exit;
}

echo json_encode(['error' => 'Unknown type']);
